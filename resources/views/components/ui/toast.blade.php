@if (session('status'))
    <div class="toast-region" aria-live="polite" aria-atomic="true">
        <div class="toast" data-toast>
            <span>{{ session('status') }}</span>
            <button class="toast-close" type="button" data-toast-close aria-label="Cerrar notificacion">
                <x-ui.icon name="x" />
            </button>
        </div>
    </div>
@endif
