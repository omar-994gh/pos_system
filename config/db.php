<?php
declare(strict_types=1);

// SQLite PDO bootstrap and lightweight migrations
// Exposes $db (PDO) for the application

$dbPath = getenv('DB_PATH') ?: (__DIR__ . '/config.sqlite');
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys = ON');

/**
 * Return true if a column exists on a table (SQLite)
 */
function sqliteColumnExists(PDO $pdo, string $table, string $column): bool {
	$stmt = $pdo->prepare("PRAGMA table_info(\"$table\")");
	$stmt->execute();
	foreach ($stmt->fetchAll() as $col) {
		if (strcasecmp((string)$col['name'], $column) === 0) {
			return true;
		}
	}
	return false;
}

// Create core tables if not exists
$db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS Users (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	username TEXT UNIQUE NOT NULL,
	password_hash TEXT NOT NULL,
	role TEXT NOT NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
SQL);

$db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS Printers (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	name TEXT NOT NULL,
	address TEXT NOT NULL,
	type TEXT DEFAULT 'wifi',
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
SQL);

$db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS Groups (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	name TEXT NOT NULL,
	printer_id INTEGER REFERENCES Printers(id) ON DELETE SET NULL
);
SQL);

$db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS Items (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	name_ar TEXT NOT NULL,
	name_en TEXT,
	barcode TEXT UNIQUE,
	group_id INTEGER REFERENCES Groups(id) ON DELETE SET NULL,
	price REAL NOT NULL,
	stock REAL DEFAULT 0,
	unit TEXT
);
SQL);

$db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS Orders (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id INTEGER REFERENCES Users(id),
	total REAL NOT NULL,
	order_seq INTEGER,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
SQL);

$db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS Order_Items (
	order_id INTEGER NOT NULL REFERENCES Orders(id) ON DELETE CASCADE,
	item_id INTEGER NOT NULL REFERENCES Items(id),
	quantity REAL NOT NULL,
	unit_price REAL NOT NULL,
	PRIMARY KEY(order_id, item_id)
);
SQL);

$db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS Warehouses (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	name TEXT NOT NULL
);
SQL);

$db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS Warehouse_Invoices (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	warehouse_id INTEGER NOT NULL REFERENCES Warehouses(id),
	supplier TEXT NOT NULL,
	date DATETIME NOT NULL,
	entry_type TEXT NOT NULL
);
SQL);

$db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS Warehouse_Invoice_Items (
	invoice_id INTEGER NOT NULL REFERENCES Warehouse_Invoices(id) ON DELETE CASCADE,
	item_id INTEGER NOT NULL REFERENCES Items(id),
	quantity REAL NOT NULL,
	unit_price REAL NOT NULL,
	total_price REAL NOT NULL,
	sale_price REAL DEFAULT 0,
	unit TEXT,
	PRIMARY KEY (invoice_id, item_id)
);
SQL);

$db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS System_Settings (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	restaurant_name TEXT,
	logo_path TEXT,
	tax_number TEXT,
	address TEXT,
	tax_rate REAL DEFAULT 0,
	print_width_mm INTEGER DEFAULT 80,
	currency TEXT DEFAULT 'USD',
	field_item_name_ar INTEGER DEFAULT 1,
	field_item_name_en INTEGER DEFAULT 0,
	field_tax_number INTEGER DEFAULT 0,
	field_username INTEGER DEFAULT 1,
	field_restaurant_name INTEGER DEFAULT 1,
	field_restaurant_logo INTEGER DEFAULT 1,
	font_size_title INTEGER DEFAULT 22,
	font_size_item INTEGER DEFAULT 16,
	font_size_total INTEGER DEFAULT 18,
	updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
SQL);

// Lightweight column migrations for older DBs
if (!sqliteColumnExists($db, 'Orders', 'order_seq')) {
	$db->exec("ALTER TABLE Orders ADD COLUMN order_seq INTEGER");
}
if (!sqliteColumnExists($db, 'Printers', 'type')) {
	$db->exec("ALTER TABLE Printers ADD COLUMN type TEXT DEFAULT 'wifi'");
}
foreach (['field_item_name_ar','field_item_name_en','field_tax_number','field_username','field_restaurant_name','field_restaurant_logo'] as $col) {
	if (!sqliteColumnExists($db, 'System_Settings', $col)) {
		$db->exec("ALTER TABLE System_Settings ADD COLUMN $col INTEGER DEFAULT 0");
	}
}
foreach ([['font_size_title',22], ['font_size_item',16], ['font_size_total',18]] as $pair) {
	[$col,$def] = $pair;
	if (!sqliteColumnExists($db, 'System_Settings', $col)) {
		$db->exec("ALTER TABLE System_Settings ADD COLUMN $col INTEGER DEFAULT $def");
	}
}

// Create authorizations mapping table
$db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS Authorizations (
	user_id INTEGER NOT NULL REFERENCES Users(id) ON DELETE CASCADE,
	element_key TEXT NOT NULL,
	is_enabled INTEGER NOT NULL DEFAULT 1,
	PRIMARY KEY(user_id, element_key)
);
SQL);

// Helpful indexes
$db->exec('CREATE INDEX IF NOT EXISTS idx_items_barcode ON Items(barcode)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_items_group ON Items(group_id)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_orders_created ON Orders(created_at)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_oi_order ON Order_Items(order_id)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_wi_date ON Warehouse_Invoices(date)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_wii_invoice ON Warehouse_Invoice_Items(invoice_id)');