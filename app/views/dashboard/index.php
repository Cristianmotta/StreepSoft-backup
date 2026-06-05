<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadistica | Streepssoft</title>
    <link rel="stylesheet" href="/streepsoft/public/css/dashboard.css">
    
</head>
<body>
    <div class="navbar">
        <nav class="main-navbar">
            <div class="navbar-group">
                <a href="#" class="navbar-item" title="Usuario">
                    <img src="/streepsoft/public/Image/usuario.png" alt="usuario" class="navbar-icon">
                </a>
                <a href="#" class="navbar-item" title="Notificaciones">
                    <img src="/streepsoft/public/Image/notifica.png" alt="notifica" class="navbar-icon">
                </a>
            </div>

            <div class="navbar-group">
                <div class="navbar-logo">
                    <img src="/streepsoft/public/Image/CopColombiaInternacional.png" alt="logo">
                </div>
                <button class="navbar-item btn-menu" onclick="toggleMenu()" aria-label="Abrir menú">
                    <img src="/streepsoft/public/Image/menu.png" alt="hamburguesa" class="navbar-icon">
                </button>
            </div>
        </nav>
        <div class="linea"></div>
    </div>
    
    <div id="side-menu" class="sidebar">
        <div class="sidebar-header">
            <img src="/streepsoft/public/Image/CopColombiaInternacional.png" alt="logo" class="sidebar-logo">
            <button class="close-icon-btn" onclick="toggleMenu()">
                <img src="/streepsoft/public/Image/menu.png" alt="cerrar">
            </button>
        </div>

        <nav class="sidebar-links">
            <a href="#">Estadísticas</a>
            <a href="#">Gestión de alumnos</a>
            <a href="/streepsoft/app/views/jugadores/show.php">Perfil de alumnos</a>
            <a href="#">Actualización de datos</a>
            <a href="#">Registro de deuda</a>
        </nav>

        <div class="sidebar-footer">
            <button class="btn-cerrar-sesion" onclick="toggleMenu()">Cerrar</button>
        </div>
    </div>

    <div id="overlay" class="overlay" onclick="toggleMenu()"></div>


    <section class="sec-statistics">
        <h1 class="h1-statistics">Estadistica</h1>

        <div class="card-statistics">
            <!-- Contenedor para alinear el select a la derecha -->
            <div class="chart-header">
                <div class="select-wrapper">
                    <label for="year-select">Año</label>
                    <select id="year-select" class="custom-select">
                        <option value="2026">2026</option>
                        <option value="2025">2025</option>
                    </select>
                </div>
            </div>
            
            <div class="chart-container">
                <canvas id="grafica"></canvas>
            </div>
        </div>
    </section>


    <div class="dashboard-container">
        <div class="dashboard-grid">
            <a href="#" class="custom-card">
                <div class="card-icon">
                    <img src="/streepsoft/public/Image/Gestion.png" alt="icon">
                </div>
                <div class="card-body">
                    <h2 class="card-title">Gestion de alumnos</h2>
                    <div class="card-divider"></div>
                    <p class="card-subtitle">Ver alumnos</p>
                </div>
            </a>

            <a href="/streepsoft/app/views/jugadores/show.php" class="custom-card">
                <div class="card-icon">
                    <img src="/streepsoft/public/Image/Perfil.png" alt="icon">
                </div>
                <div class="card-body">
                    <h2 class="card-title">Perfil de Alumno</h2>
                    <div class="card-divider"></div>
                    <p class="card-subtitle">Ver alumnos</p>
                </div>
            </a>

            <a href="#" class="custom-card">
                <div class="card-icon">
                    <img src="/streepsoft/public/Image/actualizacion.png" alt="icon">
                </div>
                <div class="card-body">
                    <h2 class="card-title">Actualizacion de datos</h2>
                    <div class="card-divider"></div>
                    <p class="card-subtitle">Ver alumnos</p>
                </div>
            </a>
        </div>
    </div>
    
    <script src="/streepsoft/public/js/dashboard/hamburguesa.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/streepsoft/public/js/dashboard/statistic.js"></script>
   
</body>
</html>