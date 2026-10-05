<?php
declare(strict_types=1);

class Deuda extends Model
{
    // Días de gracia antes de vencer en los que se muestra "pendiente"
    private const DIAS_GRACIA_ADVERTENCIA = 5;

    // Días después de pagar en los que se sigue mostrando "Pago"
    private const DIAS_PARA_VIGENTE = 2;

    public function obtenerTodasConEstado(): array
    {
        $sql = "
            SELECT DISTINCT
                d.id_deudas,
                d.id_jugadores,
                d.matricula,
                d.mes,
                d.anio,
                d.totalidad,
                d.fecha_limite_pago,
                d.fecha_pago,
                d.pago,
                d.concepto,
                d.descuento_porcentaje,
                d.valor_pagado,
                d.id_tipo_becas,
                mp.tipo_metodo_pago AS metodo_pago,
                j.nombres,
                j.apellidos,
                j.foto,
                c.nombre   AS categoria,
                tb.nombre  AS tipo_beca
            FROM deudas d
            INNER JOIN jugadores j  ON j.id_jugadores  = d.id_jugadores
            LEFT  JOIN categorias c ON c.id_categorias = j.id_categorias
            LEFT  JOIN tipos_beca tb ON tb.id_tipo_beca = d.id_tipo_becas
            LEFT  JOIN metodo_pago mp ON mp.id_metodo_pago = d.id_metodo_pago
            ORDER BY d.fecha_limite_pago ASC
        ";

        $filas = $this->query($sql);

        // IMPORTANTE: el array completo (plural, $filas) y la variable
        // de cada elemento dentro del foreach (singular, $fila) deben
        // tener nombres DISTINTOS, y el foreach debe ser "as &$fila"
        // (con el "&") para que el array_merge de abajo sí quede
        // guardado en el array que se devuelve al final.
        foreach ($filas as &$fila) {
            $fila = array_merge($fila, $this->calcularEstadoVisual($fila));
        }
        unset($fila);

        return $filas;
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "
            SELECT
                d.*,
                j.nombres,
                j.apellidos,
                tb.nombre AS tipo_beca
            FROM deudas d
            INNER JOIN jugadores j ON j.id_jugadores = d.id_jugadores
            LEFT JOIN tipos_beca tb ON tb.id_tipo_beca = d.id_tipo_becas
            WHERE d.id_deudas = ?
            LIMIT 1
        ";

        return $this->queryOne($sql, [$id]);
    }

    public function obtenerResumen(): array
    {
        $sql = "
            SELECT
                COUNT(*)                                              AS total_alumnos,
                COALESCE(SUM(totalidad), 0)                           AS total_general,
                COALESCE(SUM(CASE WHEN pago = 'pendiente' THEN totalidad END), 0) AS total_pendiente,
                COALESCE(SUM(CASE WHEN pago = 'mora'      THEN totalidad END), 0) AS total_mora,
                COALESCE(SUM(CASE WHEN pago = 'pagado'    THEN COALESCE(valor_pagado, totalidad) END), 0) AS total_recaudo
            FROM deudas
        ";

        $resumen = $this->queryOne($sql) ?? [
            'total_alumnos'   => 0,
            'total_general'   => 0,
            'total_pendiente' => 0,
            'total_mora'      => 0,
            'total_recaudo'   => 0,
        ];

        $total = (float) $resumen['total_general'];
        $resumen['porcentaje_pendiente'] = $total > 0 ? round(($resumen['total_pendiente'] / $total) * 100) : 0;
        $resumen['porcentaje_mora']      = $total > 0 ? round(($resumen['total_mora'] / $total) * 100) : 0;
        $resumen['porcentaje_recaudo']   = $total > 0 ? round(($resumen['total_recaudo'] / $total) * 100) : 0;

        return $resumen;
    }

    public function crear(array $datos): int
    {
        $sql = "
            INSERT INTO deudas (
                id_jugadores, matricula, mes, anio, totalidad,
                fecha_limite_pago, id_tipo_becas, pago
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')
        ";

        $exito = $this->execute($sql, [
            $datos['id_jugadores'],
            $datos['matricula'] ?? 0,
            $datos['mes'],
            $datos['anio'],
            $datos['totalidad'],
            $datos['fecha_limite_pago'],
            $datos['id_tipo_becas'],
        ]);

        return $exito ? $this->lastInsertId() : 0;
    }

    /* Primer ciclo de pago de jugador */
    public function crearInicial(array $datos): int
    {
        $sql = "
            INSERT INTO deudas (
                id_jugadores,
                matricula,
                mes,
                anio,
                totalidad,
                fecha_limite_pago,
                fecha_pago,
                id_metodo_pago,
                id_tipo_becas,
                concepto,
                valor_pagado,
                pago
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pagado')
        ";

        $exito = $this->execute($sql, [
            $datos['id_jugadores'],
            $datos['matricula'],
            $datos['mes'],
            $datos['anio'],
            $datos['totalidad'],
            $datos['fecha_pago'],       // fecha_limite_pago
            $datos['fecha_pago'],       // fecha_pago
            $datos['id_metodo_pago'],   // correcto
            $datos['id_tipo_becas'],    // correcto
            $datos['concepto'],
            $datos['valor_pagado'],
        ]);

        return $exito ? $this->lastInsertId() : 0;
    }


    /**
     * Registra el pago y completa el ciclo:
     * 1) mueve la deuda pagada a historial_deuda;
     * 2) elimina la deuda de la tabla activa;
     * 3) crea automáticamente la deuda del siguiente mes.
     *
     * La operación es transaccional: si cualquiera de los pasos falla,
     * todo se revierte.
     */
    public function registrarPago(int $idDeuda, array $datos): bool
    {
        if ($idDeuda <= 0) {
            throw new InvalidArgumentException('ID de deuda inválido');
        }

        $this->pdo->beginTransaction();

        try {
            $deuda = $this->queryOne(
                'SELECT * FROM deudas WHERE id_deudas = ? FOR UPDATE',
                [$idDeuda]
            );

            if ($deuda === null) {
                throw new RuntimeException('La deuda no existe o ya fue procesada');
            }

            if (($deuda['pago'] ?? '') === 'pagado') {
                throw new RuntimeException('La deuda ya está marcada como pagada');
            }

            // Preparar los datos finales que quedarán en historial.
            $deuda['fecha_pago'] = $datos['fecha_pago'];
            $deuda['id_metodo_pago'] = $datos['id_metodo_pago'];
            $deuda['concepto'] = $datos['concepto'] ?? ($deuda['concepto'] ?? null);
            $deuda['descuento_porcentaje'] = $datos['descuento_porcentaje'] ?? 0;
            $deuda['valor_pagado'] = $datos['valor_pagado'];

            $historialModel = new HistorialDeuda($this->pdo);

            if (!$historialModel->archivar($deuda)) {
                throw new RuntimeException('No se pudo archivar la deuda en el historial');
            }

            // La deuda pagada deja de pertenecer a la tabla de deudas activas.
            if (!$this->execute(
                'DELETE FROM deudas WHERE id_deudas = ?',
                [$idDeuda]
            )) {
                throw new RuntimeException('No se pudo retirar la deuda pagada de la lista activa');
            }

            // Crear el siguiente ciclo mensual.
            $this->crearSiguienteDeuda($deuda);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log('Deuda::registrarPago: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crea la deuda mensual inmediatamente posterior al ciclo pagado.
     * No crea duplicados y no genera mensualidad para jugadores inactivos.
     */
    private function crearSiguienteDeuda(array $deuda): int
    {
        $idJugador = (int) $deuda['id_jugadores'];

        $jugador = $this->queryOne(
            'SELECT estado FROM jugadores WHERE id_jugadores = ? LIMIT 1',
            [$idJugador]
        );

        if ($jugador === null) {
            throw new RuntimeException('El jugador asociado a la deuda no existe');
        }

        if (($jugador['estado'] ?? 'activo') !== 'activo') {
            // Jugador retirado/inactivo: no se genera el siguiente ciclo.
            return 0;
        }

        $meses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        $mesAnterior = array_search($deuda['mes'], $meses, true);

        if ($mesAnterior === false) {
            throw new RuntimeException('Mes inválido en la deuda pagada');
        }

        $mesSiguiente = $mesAnterior + 1;
        $anioSiguiente = (int) $deuda['anio'];

        if ($mesSiguiente > 12) {
            $mesSiguiente = 1;
            $anioSiguiente++;
        }

        $nombreMesSiguiente = $meses[$mesSiguiente];

        // Seguridad contra duplicados.
        $yaExiste = $this->queryOne(
            'SELECT id_deudas
             FROM deudas
             WHERE id_jugadores = ? AND mes = ? AND anio = ?
             LIMIT 1',
            [$idJugador, $nombreMesSiguiente, $anioSiguiente]
        );

        if ($yaExiste !== null) {
            return (int) $yaExiste['id_deudas'];
        }

        // El siguiente mes es solamente mensualidad: la matrícula no se repite.
        $mensualidad = max(
            0,
            (float) $deuda['totalidad'] - (float) ($deuda['matricula'] ?? 0)
        );

        // Día de cobro configurable desde la tabla configuracion.
        $configuracion = new Configuracion($this->pdo);
        $diaCobro = (int) ($configuracion->obtenerPorClave('dia_cobro') ?? 5);
        $diaCobro = max(1, min(31, $diaCobro));

        $diasMes = cal_days_in_month(
            CAL_GREGORIAN,
            $mesSiguiente,
            $anioSiguiente
        );

        $diaReal = min($diaCobro, $diasMes);
        $fechaLimite = sprintf(
            '%04d-%02d-%02d',
            $anioSiguiente,
            $mesSiguiente,
            $diaReal
        );

        $sql = "
            INSERT INTO deudas (
                id_jugadores,
                matricula,
                mes,
                anio,
                totalidad,
                fecha_limite_pago,
                id_tipo_becas,
                pago,
                concepto
            ) VALUES (?, 0, ?, ?, ?, ?, ?, 'pendiente', ?)
        ";

        $exito = $this->execute($sql, [
            $idJugador,
            $nombreMesSiguiente,
            $anioSiguiente,
            $mensualidad,
            $fechaLimite,
            (int) $deuda['id_tipo_becas'],
            'Mensualidad ' . $nombreMesSiguiente . ' ' . $anioSiguiente,
        ]);

        if (!$exito) {
            throw new RuntimeException('No se pudo crear la deuda del siguiente mes');
        }

        return $this->lastInsertId();
    }

    public function marcarVencidaComoMora(): int
    {
        $sql = "
            UPDATE deudas
            SET pago = 'mora'
            WHERE pago = 'pendiente'
              AND fecha_limite_pago < CURDATE()
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Calcula el estado VISUAL de una deuda (vigente / pendiente / mora / pago).
     * "vigente" no es un valor guardado en la BD -- es un adorno visual que
     * aparece cuando ya se pagó (2+ días después) o cuando falta bastante
     * para el vencimiento.
     */
    public function calcularEstadoVisual(array $fila): array
    {
        $hoy = new DateTimeImmutable('today');
        $fechaLimite = new DateTimeImmutable($fila['fecha_limite_pago']);

        $resultado = [
            'estado_visual' => 'pendiente',
            'vigencia_texto' => '',
            'advertencia_texto' => '',
        ];

        // --- Ya está pagada ---
        if ($fila['pago'] === 'pagado') {
            $diasDesdePago = null;
            if (!empty($fila['fecha_pago'])) {
                $fechaPago = new DateTimeImmutable($fila['fecha_pago']);
                $diasDesdePago = $hoy->diff($fechaPago)->days;
            }

            if ($diasDesdePago !== null && $diasDesdePago < self::DIAS_PARA_VIGENTE) {
                $resultado['estado_visual'] = 'pago';
                $resultado['vigencia_texto'] = $diasDesdePago === 0
                    ? 'hoy'
                    : 'hace ' . $diasDesdePago . ' día' . ($diasDesdePago === 1 ? '' : 's');
            } else {
                $resultado['estado_visual'] = 'vigente';

                if ($fechaLimite >= $hoy) {
                    $diasParaVencer = $hoy->diff($fechaLimite)->days;
                    $resultado['vigencia_texto'] = 'vence en ' . $diasParaVencer . ' día' . ($diasParaVencer === 1 ? '' : 's');
                } else {
                    $resultado['vigencia_texto'] = 'al día';
                }
            }

            return $resultado;
        }

        // --- Ya venció (o la BD ya la marcó como mora) ---
        if ($fila['pago'] === 'mora' || $fechaLimite < $hoy) {
            $diasMora = $fechaLimite->diff($hoy)->days;
            $resultado['estado_visual'] = 'mora';
            $resultado['vigencia_texto'] = 'vencido hace ' . $diasMora . ' día' . ($diasMora === 1 ? '' : 's');
            $resultado['advertencia_texto'] = '¡' . $diasMora . ' día' . ($diasMora === 1 ? '' : 's') . ' de mora! Más de 30 días se considera inactivo';
            return $resultado;
        }

        // --- Todavía no vence ---
        $diasParaVencer = $hoy->diff($fechaLimite)->days;
        $textoVence = 'vence en ' . $diasParaVencer . ' día' . ($diasParaVencer === 1 ? '' : 's');

        if ($diasParaVencer > self::DIAS_GRACIA_ADVERTENCIA) {
            $resultado['estado_visual'] = 'vigente';
            $resultado['vigencia_texto'] = $textoVence;
        } else {
            $resultado['estado_visual'] = 'pendiente';
            $resultado['vigencia_texto'] = $textoVence;
            $resultado['advertencia_texto'] = '¡Tiene ' . $diasParaVencer . ' día' . ($diasParaVencer === 1 ? '' : 's') . ' de gracia para hacer el pago!';
        }

        return $resultado;
    }
}