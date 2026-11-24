<?php
use Lebleu\Compartido\Campos as C;
use Lebleu\Compartido\Formato as F;
/** @var array $usuarios @var array $socios @var int $yoId */
$roles = ['admin' => 'Administrador', 'capitan' => 'Capitán', 'socio' => 'Socio'];
?>
<div class="wrap">
  <h1 style="margin:4px 0 2px;font-size:22px">Usuarios</h1>
  <div class="hint" style="margin-bottom:18px">Da de alta al capitán y a los socios. Cada quien entra con su correo y contraseña.</div>

  <div class="table-card" style="margin-bottom:22px" data-refrescable="usuarios">
    <table>
      <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Socio</th><th class="tc">Acciones</th></tr></thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
        <tr>
          <td><?= F::e($u['nombre']) ?></td><td><?= F::e($u['email']) ?></td>
          <td><span class="tag b"><?= F::e($roles[$u['rol']] ?? $u['rol']) ?></span></td>
          <td><?= F::e($u['socio_nombre'] ?? '—') ?></td>
          <td class="tc" style="white-space:nowrap">
            <button class="btn-link" data-clave="<?= $u['id'] ?>" data-nombre="<?= F::e($u['nombre']) ?>">Contraseña</button><?php if ($u['id'] !== $yoId): ?><button class="btn-link danger" data-borrar-usuario="<?= $u['id'] ?>" data-nombre="<?= F::e($u['nombre']) ?>">Borrar</button><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form class="form-card" data-form data-url="/api/usuarios" data-metodo="POST" data-limpiar="nombre,email,password">
    <h2>Nueva cuenta</h2>
    <div class="grid">
      <?= C::campo('Nombre', C::input('nombre', '', ['required' => true])) ?>
      <?= C::campo('Correo', C::input('email', '', ['type' => 'email', 'required' => true])) ?>
      <?= C::campo('Rol', C::select('rol', ['capitan' => 'Capitán', 'socio' => 'Socio', 'admin' => 'Administrador'], 'capitan', ['data-rol' => true])) ?>
      <?= C::campo('Socio asociado', C::select('socio_id', array_column($socios, 'nombre', 'id'), (string) ($socios[0]['id'] ?? '')), '', 'data-solo-socio hidden') ?>
      <?= C::campo('Contraseña (mínimo 6)', C::input('password', '', ['type' => 'text', 'required' => true, 'minlength' => '6', 'placeholder' => 'La comparten al usuario'])) ?>
    </div>
    <div style="margin-top:16px;display:flex;align-items:center;gap:14px">
      <button class="btn" data-boton data-texto="Crear cuenta">Crear cuenta</button>
      <span data-mensaje hidden></span>
    </div>
  </form>
</div>
