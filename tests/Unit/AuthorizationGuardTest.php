<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The mechanical guard.
 *
 * Eighteen Livewire components shipped with public delete/save/update methods
 * and no authorization at all — a viewer could delete a speaker, a venue room,
 * a client. Every one of those components looked exactly like its gated
 * siblings; the missing line was invisible on read-through, and it was missed
 * repeatedly, including by the session that added the very last one of them.
 *
 * This test reads the actual source of every public method a Livewire
 * component declares. If the body writes to the database (create/update/
 * delete/save/attach/detach/sync/increment/decrement) and nowhere calls
 * Gate::authorize or $this->authorize, the test fails and names the exact
 * class and method — the same shape of check a human reviewer would do, run
 * on every commit instead of remembered.
 *
 * EXEMPT lists the two legitimate ways a mutating method is not gated in its
 * own body: it delegates to a private method on the same class that gates
 * once for all of them (RoomLayoutBuilder::persist(),
 * ExhibitionFloorPlan::persistDims()), or it is a Livewire lifecycle hook
 * with no meaningful "unauthorized" state (mount, render, boot, hydrate).
 * Adding to EXEMPT is a decision to leave a method ungated — it should be rare
 * and each entry should be defensible on its own.
 */
class AuthorizationGuardTest extends TestCase
{
    /** Framework lifecycle hooks — never reachable as a standalone unauthorized action. */
    private const LIFECYCLE = [
        'mount', 'boot', 'booted', 'render', 'hydrate', 'dehydrate',
        'updating', 'updated', '__invoke', '__construct',
    ];

    /**
     * Class::method pairs that write to the database but are deliberately
     * left ungated in their own body, because a private method on the same
     * class gates the write for every caller. See that method's own
     * Gate::authorize call for the actual check.
     */
    private const DELEGATES_TO_A_GATED_PRIVATE_METHOD = [
        //
        // ── deliberately public, by design — not an authorization gap ──
        //
        // These two routes exist BECAUSE the visitor is not signed in: a
        // stranger filling in the registration form, a door volunteer with a
        // borrowed phone scanning a badge. Each is reached by an unguessable
        // token rather than a role, per its own file's doc comment. Adding
        // Gate::authorize here would not add security — it would break the
        // feature, since there is no session to hold a role in the first
        // place. (The token itself is sequential-ID-derived and worth
        // hardening — tracked separately, not something this test can check.)
        'App\\Livewire\\PublicRegistration::register',
        'App\\Livewire\\CheckInScan::admit',
        'App\\Livewire\\CheckInScan::admitToSession',

        // RoomLayoutBuilder — every canvas mutation funnels through persist().
        'App\\Livewire\\RoomLayoutBuilder::addElement',
        'App\\Livewire\\RoomLayoutBuilder::moveElement',
        'App\\Livewire\\RoomLayoutBuilder::rotate',
        'App\\Livewire\\RoomLayoutBuilder::rotateBy',
        'App\\Livewire\\RoomLayoutBuilder::setRotation',
        'App\\Livewire\\RoomLayoutBuilder::changeSeats',
        'App\\Livewire\\RoomLayoutBuilder::resizeElement',
        'App\\Livewire\\RoomLayoutBuilder::setSizeMeters',
        'App\\Livewire\\RoomLayoutBuilder::deleteSelected',
        'App\\Livewire\\RoomLayoutBuilder::updateSeatblock',
        'App\\Livewire\\RoomLayoutBuilder::nameElement',
        'App\\Livewire\\RoomLayoutBuilder::removeElement',
        'App\\Livewire\\RoomLayoutBuilder::clearAll',
        'App\\Livewire\\RoomLayoutBuilder::updatedWidthM',
        'App\\Livewire\\RoomLayoutBuilder::updatedLengthM',
        'App\\Livewire\\RoomLayoutBuilder::generateSeating',
        'App\\Livewire\\RoomLayoutBuilder::designUShape',
        // ExhibitionFloorPlan — the two hall-dimension updaters share persistDims().
        'App\\Livewire\\ExhibitionFloorPlan::updatedHallW',
        'App\\Livewire\\ExhibitionFloorPlan::updatedHallL',
    ];

    /** DB-mutation call shapes worth flagging. */
    private const MUTATING_PATTERNS = [
        '->create(', '->update(', '->updateOrCreate(', '->firstOrCreate(',
        '->delete(', '->forceDelete(', '->restore(', '->save(',
        '->attach(', '->detach(', '->sync(', '->syncWithoutDetaching(',
        '->increment(', '->decrement(', 'DB::table(', 'DB::insert(', 'DB::update(', 'DB::delete(',
    ];

    private const AUTHORIZATION_CALLS = ['Gate::authorize(', '$this->authorize(', 'Gate::allows(', 'abort_unless(Gate::'];

    public function test_every_mutating_livewire_method_is_authorized(): void
    {
        $violations = [];

        foreach ($this->livewireClasses() as $class) {
            $ref = new ReflectionClass($class);

            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Only methods this class itself declares — not Livewire\Component's,
                // not a trait's (RoutesCostsToBudget/BulkSelectable already answer for themselves).
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                $name = $method->getName();
                if (in_array($name, self::LIFECYCLE, true)) {
                    continue;
                }

                // Livewire's own WithFileUploads trait reports its methods as
                // "declared" on whichever component uses it (a PHP reflection
                // quirk for trait-inserted methods) — but the source lives in
                // vendor/, so it is framework machinery, not app code this
                // test can hold responsible.
                if (str_contains($method->getFileName(), '/vendor/')) {
                    continue;
                }

                $key = $class.'::'.$name;
                if (in_array($key, self::DELEGATES_TO_A_GATED_PRIVATE_METHOD, true)) {
                    continue;
                }

                $body = $this->methodSource($method);
                $mutates = $this->containsAny($body, self::MUTATING_PATTERNS);
                $authorizes = $this->containsAny($body, self::AUTHORIZATION_CALLS);

                if ($mutates && ! $authorizes) {
                    $violations[] = $key;
                }
            }
        }

        $this->assertSame([], $violations, "These Livewire methods write to the database with no authorization check:\n"
            .implode("\n", array_map(fn ($v) => "  - $v", $violations))
            ."\n\nEither add Gate::authorize(...) as the method's first line, or — if it delegates to a\n"
            .'private method on the same class that already gates the write — add it to '
            .'AuthorizationGuardTest::DELEGATES_TO_A_GATED_PRIVATE_METHOD with a comment explaining why.');
    }

    /** @return list<class-string> */
    private function livewireClasses(): array
    {
        $classes = [];
        $base = dirname(__DIR__, 2).'/app/Livewire';

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // Concerns are traits mixed into components, not components themselves —
            // a class using one is scanned, and the trait's own methods are skipped
            // above (declaringClass check), so nothing here is left unchecked.
            if (str_contains($file->getPathname(), '/Concerns/')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (! preg_match('/^namespace\s+([^;]+);/m', $source, $ns)) {
                continue;
            }
            if (! preg_match('/^class\s+(\w+)/m', $source, $cls)) {
                continue;
            }

            $fqcn = trim($ns[1]).'\\'.$cls[1];
            if (is_subclass_of($fqcn, \Livewire\Component::class)) {
                $classes[] = $fqcn;
            }
        }

        sort($classes);

        return $classes;
    }

    private function methodSource(ReflectionMethod $method): string
    {
        $lines = file($method->getFileName());
        $start = $method->getStartLine() - 1;
        $end = $method->getEndLine();

        return implode('', array_slice($lines, $start, $end - $start));
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
