<nav class="navbar navbar-expand-lg bg-body-tertiary">
  <div class="container-fluid">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link active" aria-current="page" href="{{ route('index') }}">Inicio</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('profile') }}">Perfil</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('videos') }}">Clases</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('usuarios.index') }}">Usuarios</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('suscripciones.index') }}">Compras</a>
        </li>
        <li class="nav-item">
          <form action="{{ route('logout') }}" method="post">
            @csrf
            <button type="submit">Cerrar sesión</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</nav>