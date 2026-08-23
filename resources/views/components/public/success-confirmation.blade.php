@props([
    'product',
    'radicado',
    'alreadyCompleted' => false,
])

<article class="success-receipt" aria-labelledby="success-title">
    <div class="success-topbar">
        <div class="success-brand">
            <span class="success-brand-logo-frame" aria-hidden="true">
                <img class="success-brand-logo" src="{{ asset('img/series-logo-sinbg.png') }}" alt="">
            </span>
            <strong>Series Agencia de Seguros</strong>
        </div>
        <a href="{{ route('home') }}">Volver al sitio</a>
    </div>

    <div class="success-body">
        <span class="success-check" aria-hidden="true">
            <span></span>
        </span>

        <header class="success-heading">
            <h1 id="success-title">Solicitud recibida</h1>
            <p>Guarda tu numero de radicado para consultar el estado mas adelante.</p>
        </header>

        <div class="success-radicado">
            <span>Radicado {{ $product }}</span>
            <strong>{{ $radicado }}</strong>
        </div>

        <dl class="success-status">
            <dt>Estado actual</dt>
            <dd>Recibido</dd>
        </dl>

        @if ($alreadyCompleted)
            <x-ui.alert type="info">
                Esta solicitud ya habia sido completada.
            </x-ui.alert>
        @endif

        <div class="success-message">
            <p><strong>Su solicitud ha sido enviada con exito.</strong></p>
            <p>El numero de radicado es el que llego en un mensaje de texto a su celular. A partir de su fecha de radicacion el seguro estara siendo cancelado. Por favor tenga presente que, si la informacion diligenciada no es correcta, el seguro NO estara siendo cancelado con exito. Para conocer el estado de la cancelacion usted podra ingresar al link <a href="https://portal.correovalle.com/progreser">portal.correovalle.com/progreser</a>.</p>
            <p>Si usted cuenta con un Credito a traves de progreSER, la entidad tendra 15 dias habiles para hacer el proceso de inactivacion del cobro en su credito. Para saber el estado despues de este tiempo puede contactarse al correo: <a href="mailto:contactenos@progreser.com">contactenos@progreser.com</a>.</p>
        </div>
    </div>
</article>
