<?php
class Printer
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** جلب كل الطابعات */
    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM Printers ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** جلب طابعة واحدة */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM Printers WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** إنشاء طابعة */
    public function create(string $name, string $address, string $type): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO Printers (name, address, type, created_at) VALUES (:name, :address, :type, CURRENT_TIMESTAMP)'
        );
        return $stmt->execute([
            ':name'    => $name,
            ':address' => $address,
            ':type'    => $type,
        ]);
    }

    /** تعديل طابعة */
    public function update(int $id, string $name, string $address, string $type): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE Printers SET name = :name, address = :address, type = ;type WHERE id = :id'
        );
        return $stmt->execute([
            ':name'    => $name,
            ':address' => $address,
            ':id'      => $id,
            ':type'    => $type,
        ]);
    }

    /** حذف طابعة */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM Printers WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
