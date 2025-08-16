<?php
class Group
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** جلب كل المجموعات */
    public function all(): array
    {
        $stmt = $this->db->query('
            SELECT g.id, g.name, p.name AS printer_name
            FROM Groups g
            LEFT JOIN Printers p ON g.printer_id = p.id
            ORDER BY g.name
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** جلب مجموعة واحدة */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM Groups WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** إنشاء مجموعة جديدة */
    public function create(string $name, ?int $printerId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO Groups (name, printer_id) VALUES (:name, :printer_id)'
        );
        return $stmt->execute([
            ':name'       => $name,
            ':printer_id' => $printerId,
        ]);
    }

    /** تعديل مجموعة */
    public function update(int $id, string $name, ?int $printerId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE Groups SET name = :name, printer_id = :printer_id WHERE id = :id'
        );
        return $stmt->execute([
            ':name'       => $name,
            ':printer_id' => $printerId,
            ':id'         => $id,
        ]);
    }

    /** حذف مجموعة */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM Groups WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
