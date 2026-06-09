<?php

class JugadorController {
    private $pdo;

    public function __construct($pdo){
        $this->pdo = $pdo;
    }

    // LISTA A LOS JUGADORES //

    public function index() {
        $stmt = $this->pdo->prepare(
            "SELECT j. * , i.nombre AS instructor_nombre, c.nombre AS categoria_nombre
            FROM jugadores j
            LEFT JOIN instructores i ON j.instructor_id = i.id 
            LEFT JOIN categorias c ON i.categoria_id = c.id 
            ORDER BY j.apellido ASC"
        );

        $stmt->execute();
        $jugadores = $stmt->fetchAll();
        require_once 'app/views/jugadores/index.php';
    }

    // MUESTRA EL FORMULARIO PARA LOS NUEVOS JUGADORES //
        // ─── MOSTRAR FORMULARIO NUEVO JUGADOR ───
    public function create() {
        $instructores = $this->getInstructores();
        require_once 'app/views/jugadores/create.php';
    }

    // ─── GUARDAR NUEVO JUGADOR ───
    public function store() {
        $apellido          = trim($_POST['apellido'] ?? '');
        $nombre            = trim($_POST['nombre'] ?? '');
        $talla             = trim($_POST['talla'] ?? '');
        $iniciales         = trim($_POST['iniciales'] ?? '');
        $camiseta          = trim($_POST['camiseta'] ?? '');
        $fecha_nacimiento  = trim($_POST['fecha_nacimiento'] ?? '');
        $edad              = trim($_POST['edad'] ?? '');
        $documento         = trim($_POST['documento'] ?? '');
        $celular_acudiente = trim($_POST['celular_acudiente'] ?? '');
        $instructor_id     = trim($_POST['instructor_id'] ?? '');
        $eps               = trim($_POST['eps'] ?? '');
        $fecha_inscripcion = trim($_POST['fecha_inscripcion'] ?? '');
        $tipo_beca         = trim($_POST['tipo_beca'] ?? 'sin_beca');
        $error             = null;

        // Validaciones
        if(empty($apellido) || empty($nombre) || empty($documento)) {
            $error = "Apellido, nombre y documento son obligatorios.";
            $instructores = $this->getInstructores();
            require_once 'app/views/jugadores/create.php';
            return;
        }

        // Verificar documento duplicado
        $stmt = $this->pdo->prepare(
            "SELECT id FROM jugadores WHERE documento = :documento"
        );
        $stmt->execute([':documento' => $documento]);
        if($stmt->fetch()) {
            $error = "Ya existe un jugador con ese número de documento.";
            $instructores = $this->getInstructores();
            require_once 'app/views/jugadores/create.php';
            return;
        }

        // Verificar camiseta duplicada
        if(!empty($camiseta)) {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM jugadores WHERE camiseta = :camiseta"
            );
            $stmt->execute([':camiseta' => $camiseta]);
            if($stmt->fetch()) {
                $error = "Ya existe un jugador con ese número de camiseta.";
                $instructores = $this->getInstructores();
                require_once 'app/views/jugadores/create.php';
                return;
            }
        }

        // Insertar jugador
        $stmt = $this->pdo->prepare(
            "INSERT INTO jugadores (
                apellido, nombre, talla, iniciales, camiseta,
                fecha_nacimiento, edad, documento, celular_acudiente,
                instructor_id, eps, fecha_inscripcion, tipo_beca
            ) VALUES (
                :apellido, :nombre, :talla, :iniciales, :camiseta,
                :fecha_nacimiento, :edad, :documento, :celular_acudiente,
                :instructor_id, :eps, :fecha_inscripcion, :tipo_beca
            )"
       );
        $stmt->execute([
            ':apellido'          => $apellido,
            ':nombre'            => $nombre,
            ':talla'             => $talla,
            ':iniciales'         => $iniciales,
            ':camiseta'          => $camiseta ?: null,
            ':fecha_nacimiento'  => $fecha_nacimiento ?: null,
            ':edad'              => $edad ?: null,
            ':documento'         => $documento,
            ':celular_acudiente' => $celular_acudiente,
            ':instructor_id'     => $instructor_id ?: null,
            ':eps'               => $eps,
            ':fecha_inscripcion' => $fecha_inscripcion ?: null,
            ':tipo_beca'         => $tipo_beca
        ]);

        $jugador_id = $this->pdo->lastInsertId();

        // Crear registro de documentos automáticamente
        $stmt = $this->pdo->prepare(
            "INSERT INTO documentos (jugador_id) VALUES (:jugador_id)"
        );
        $stmt->execute([':jugador_id' => $jugador_id]);

        // Redirigir al listado
        header('Location:/streepsoft/public/jugadores');
        exit;
    }

    // ─── MOSTRAR FORMULARIO EDITAR ───
    public function edit($id) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM jugadores WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $jugador = $stmt->fetch();

        if(!$jugador) {
             header('Location:/streepsoft/public/jugadores');
            exit;
        }

        $instructores = $this->getInstructores();
        require_once 'app/views/jugadores/edit.php';
    }

    // ─── ACTUALIZAR JUGADOR ───
    public function update($id) {
        $apellido          = trim($_POST['apellido'] ?? '');
        $nombre            = trim($_POST['nombre'] ?? '');
        $talla             = trim($_POST['talla'] ?? '');
        $iniciales         = trim($_POST['iniciales'] ?? '');
        $camiseta          = trim($_POST['camiseta'] ?? '');
        $fecha_nacimiento  = trim($_POST['fecha_nacimiento'] ?? '');
        $edad              = trim($_POST['edad'] ?? '');
        $documento         = trim($_POST['documento'] ?? '');
        $celular_acudiente = trim($_POST['celular_acudiente'] ?? '');
        $instructor_id     = trim($_POST['instructor_id'] ?? '');
        $eps               = trim($_POST['eps'] ?? '');
        $fecha_inscripcion = trim($_POST['fecha_inscripcion'] ?? '');
        $tipo_beca         = trim($_POST['tipo_beca'] ?? 'sin_beca');
        $estado            = trim($_POST['estado'] ?? 'activo');
        $error             = null;

        if(empty($apellido) || empty($nombre) || empty($documento)) {
            $error = "Apellido, nombre y documento son obligatorios.";
            $instructores = $this->getInstructores();
            require_once 'app/views/jugadores/edit.php';
            return;
        }

        // Verificar documento duplicado excluyendo el jugador actual
        $stmt = $this->pdo->prepare(
            "SELECT id FROM jugadores WHERE documento = :documento AND id != :id"
        );
        $stmt->execute([':documento' => $documento, ':id' => $id]);
        if($stmt->fetch()) {
            $error = "Ya existe otro jugador con ese número de documento.";
            $instructores = $this->getInstructores();
            require_once 'app/views/jugadores/edit.php';
            return;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE jugadores SET
                apellido = :apellido,
                nombre = :nombre,
                talla = :talla,
                iniciales = :iniciales,
                camiseta = :camiseta,
                fecha_nacimiento = :fecha_nacimiento,
                edad = :edad,
                documento = :documento,
                celular_acudiente = :celular_acudiente,
                instructor_id = :instructor_id,
                eps = :eps,
                fecha_inscripcion = :fecha_inscripcion,
                tipo_beca = :tipo_beca,
                estado = :estado
             WHERE id = :id"
        );
        $stmt->execute([
            ':apellido'          => $apellido,
            ':nombre'            => $nombre,
            ':talla'             => $talla,
            ':iniciales'         => $iniciales,
            ':camiseta'          => $camiseta ?: null,
            ':fecha_nacimiento'  => $fecha_nacimiento ?: null,
            ':edad'              => $edad ?: null,
            ':documento'         => $documento,
            ':celular_acudiente' => $celular_acudiente,
            ':instructor_id'     => $instructor_id ?: null,
            ':eps'               => $eps,
            ':fecha_inscripcion' => $fecha_inscripcion ?: null,
            ':tipo_beca'         => $tipo_beca,
            ':estado'            => $estado,
            ':id'                => $id
        ]);

         header('Location:/streepsoft/public/jugadores');
        exit;
    }

    // ─── CAMBIAR ESTADO ───
    public function cambiarEstado($id) {
        $estado = trim($_POST['estado'] ?? '');

        if(!in_array($estado, ['activo', 'inactivo', 'retirado'])) {
            header('Location:/streepsoft/public/jugadores');
            exit;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE jugadores SET estado = :estado WHERE id = :id"
        );
        $stmt->execute([':estado' => $estado, ':id' => $id]);

         header('Location:/streepsoft/public/jugadores');
        exit;
    }

    // ─── VER DETALLE ───
    public function show($id) {
        $stmt = $this->pdo->prepare(
            "SELECT j.*, i.nombre AS instructor_nombre, c.nombre AS categoria_nombre
             FROM jugadores j
             LEFT JOIN instructores i ON j.instructor_id = i.id
             LEFT JOIN categorias c ON i.categoria_id = c.id
             WHERE j.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $jugador = $stmt->fetch();

        if(!$jugador) {
             header('Location:/streepsoft/public/jugadores');
            exit;
        }

        // Traer documentos del jugador
        $stmt = $this->pdo->prepare(
            "SELECT * FROM documentos WHERE jugador_id = :jugador_id"
        );
        $stmt->execute([':jugador_id' => $id]);
        $documentos = $stmt->fetch();

        require_once 'app/views/jugadores/show.php';
    }

    // ─── MÉTODO PRIVADO: OBTENER INSTRUCTORES ───
    private function getInstructores() {
        $stmt = $this->pdo->prepare(
            "SELECT i.*, c.nombre AS categoria_nombre 
             FROM instructores i
             LEFT JOIN categorias c ON i.categoria_id = c.id
             WHERE i.activo = 1
             ORDER BY i.nombre ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>