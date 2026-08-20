{{--
    One confirm dialog for the whole app. Triggers call window.ebhConfirm({…});
    see <x-confirm> and resources/js/app.js. Replaces browser wire:confirm —
    same question, our chrome, Escape and click-outside cancel.
--}}
<div x-data="ebhConfirmHost"
     x-cloak
     x-show="open"
     x-on:keydown.escape.window="open && close()"
     class="fixed inset-0 z-[80] flex items-center justify-center p-4"
     dusk="confirm-dialog"
     role="alertdialog"
     aria-modal="true"
     :aria-labelledby="open ? 'ebh-confirm-title' : null"
     :aria-describedby="open && body ? 'ebh-confirm-body' : null">

    <div class="absolute inset-0 bg-eo-navy-deep/50 backdrop-blur-sm"
         x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="close()"
         aria-hidden="true"></div>

    <div class="eo-soft-card relative w-full max-w-md shadow-eo-float"
         x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-1"
         @click.stop>

        <div class="border-b border-eo-line bg-eo-workspace/60 px-5 py-4">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                      :class="{
                          'bg-eo-risk-soft text-eo-risk': tone === 'danger',
                          'bg-eo-warn-soft text-eo-warn-ink': tone === 'warn',
                          'bg-eo-bg text-eo-muted': tone === 'neutral',
                      }">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 id="ebh-confirm-title" class="text-base font-bold text-eo-text" x-text="title"></h3>
                    <p id="ebh-confirm-body" class="mt-1 whitespace-pre-line text-xs leading-relaxed text-eo-muted"
                       x-show="body" x-text="body"></p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 px-5 py-4">
            <button type="button"
                    @click="close()"
                    class="h-9 rounded-xl px-3.5 text-xs font-bold text-eo-muted transition hover:bg-eo-bg hover:text-eo-text"
                    x-text="cancelLabel"></button>
            <button type="button"
                    x-ref="confirmBtn"
                    @click="accept()"
                    class="h-9 rounded-xl px-4 text-xs font-bold text-white transition"
                    :class="{
                        'bg-eo-risk hover:brightness-90': tone === 'danger',
                        'bg-eo-warn hover:brightness-90': tone === 'warn',
                        'bg-eo-navy hover:bg-eo-navy-mid': tone === 'neutral',
                    }"
                    x-text="confirmLabel"></button>
        </div>
    </div>
</div>
