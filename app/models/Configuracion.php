<?php

class Configuracion extends Model
{
    protected static $table = 'configuracion';
    protected static $primaryKey = 'id';

    
     //Obtener todas las configuraciones
     
    public function obtenerTodas(): array
    {
        $sql = "SELECT * FROM configuracion";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
     //Obtener valor de una clave específica
     
    public function obtenerPorClave(string $clave): ?string
    {
        $sql = "SELECT valor FROM configuracion WHERE clave = :clave";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':clave' => $clave]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['valor'] ?? null;
    }

    
    //Actualizar valor de una clave
     
    public function actualizar(string $clave, string $valor): bool
    {
        $sql = "UPDATE configuracion SET valor = :valor WHERE clave = :clave";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':valor' => $valor, ':clave' => $clave]);
    }

    
    //Actualizar múltiples configuraciones
     
    public function actualizarMultiples(array $datos): bool
    {
        $this->pdo->beginTransaction();

        try {
            foreach ($datos as $clave => $valor) {
                $sql = "UPDATE configuracion SET valor = :valor WHERE clave = :clave";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':valor' => $valor, ':clave' => $clave]);
            }

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }


    public function guardar(string $clave, string $valor): bool
    {
        $sql = "
            INSERT INTO configuracion (clave, valor) VALUES (:clave, :valor)
            ON DUPLICATE KEY UPDATE valor = :valor2
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':clave' => $clave, ':valor' => $valor, ':valor2' => $valor]);
    }
}