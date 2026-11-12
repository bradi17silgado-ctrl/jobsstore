
<?php
if (!defined('JOBS_APP')) {
    http_response_code(403);
    exit('Acceso denegado');
}
?>

<section class="jobs-wrap">
    <h1>Acceso administrativo</h1>

    <form
        class="jobs-form"
        method="post"
        action="/admin/login"
    >
        <input
            type="hidden"
            name="csrf"
            value="<?= htmlspecialchars(csrf(), ENT_QUOTES, 'UTF-8') ?>"
        >

        <input
            type="password"
            name="password"
            placeholder="Contraseña"
            required
        >

        <button class="btn btn-primary" type="submit">
            Ingresar
        </button>
    </form>
</section>
