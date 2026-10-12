<?php
declare(strict_types=1);

// Database connection and schema initialization

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    $dbName = getenv('DB_NAME') ?: 'paradise_resort';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS') ?: '';

    // Ensure database exists
    $dsnMysql = "mysql:host={$dbHost};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdoServer = new PDO($dsnMysql, $dbUser, $dbPass, $options);
        $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        die('Database server connection failed: ' . htmlspecialchars($e->getMessage()));
    }

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    } catch (Throwable $e) {
        die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
    }

    initialize_database_schema($pdo);
    return $pdo;
}

function initialize_database_schema(PDO $pdo): void {
    // users
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    // rooms
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS rooms (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            price_per_night DECIMAL(10,2) NOT NULL,
            capacity INT NOT NULL DEFAULT 2,
            image_path VARCHAR(255) NOT NULL,
            amenities TEXT NULL,
            bed_type VARCHAR(100) NULL,
            size_sqm INT NULL,
            `view` VARCHAR(100) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    // Ensure new columns exist if table was created in an older version
    // Note: MariaDB/MySQL do not support placeholders with SHOW statements in prepared queries.
    // Use information_schema for a portable and safe existence check instead.
    $ensureColumn = function(string $table, string $column, string $definition) use ($pdo): void {
        $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $column]);
        $columnExists = (int)$stmt->fetchColumn() > 0;
        if (!$columnExists) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    };
    $ensureColumn('rooms', 'amenities', 'TEXT NULL');
    $ensureColumn('rooms', 'bed_type', 'VARCHAR(100) NULL');
    $ensureColumn('rooms', 'size_sqm', 'INT NULL');
    $ensureColumn('rooms', 'view', 'VARCHAR(100) NULL');

    // bookings (extend enum to include additional workflow states)
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS bookings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            room_id INT UNSIGNED NOT NULL,
            check_in DATE NOT NULL,
            check_out DATE NOT NULL,
            guests INT NOT NULL DEFAULT 1,
            status ENUM('pending','approved','rejected','cancelled','checked_in','completed','no_show') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_bookings_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
            CONSTRAINT chk_dates CHECK (check_out > check_in)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);
    // Ensure enum includes new values if table existed earlier
    try {
        $pdo->exec("ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','approved','rejected','cancelled','checked_in','completed','no_show') NOT NULL DEFAULT 'pending'");
    } catch (Throwable $e) { /* ignore if not needed */ }
    // Helpful index for availability checks (create only if missing)
    $idxCount = (int)$pdo->query("SELECT COUNT(1) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'bookings' AND index_name = 'idx_bookings_room_dates'")->fetchColumn();
    if ($idxCount === 0) {
        $pdo->exec('CREATE INDEX idx_bookings_room_dates ON bookings (room_id, check_in, check_out, status)');
    }

    // payments
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method VARCHAR(50) NOT NULL DEFAULT 'card',
            status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
            reference VARCHAR(64) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    // blocked dates for rooms (maintenance or other holds)
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS blocked_dates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            room_id INT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            reason VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_block_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    // coupons table
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS coupons (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(40) NOT NULL UNIQUE,
            type ENUM('percent','fixed') NOT NULL,
            value DECIMAL(10,2) NOT NULL,
            valid_from DATE NULL,
            valid_to DATE NULL,
            max_uses INT NULL,
            used_count INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    // seasonal price rules (multiplier)
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS price_rules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.000,
            label VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    // reviews
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS reviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            room_id INT UNSIGNED NOT NULL,
            booking_id INT UNSIGNED NULL,
            rating TINYINT NOT NULL,
            comment TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_reviews_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    // audit logs
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            action VARCHAR(64) NOT NULL,
            entity VARCHAR(64) NULL,
            entity_id INT UNSIGNED NULL,
            meta TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    // contact messages
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_contact_msg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    // contact replies (admin responses)
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS contact_replies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id INT UNSIGNED NOT NULL,
            admin_user_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NULL,
            reply TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_contact_reply_msg FOREIGN KEY (message_id) REFERENCES contact_messages(id) ON DELETE CASCADE,
            CONSTRAINT fk_contact_reply_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_contact_reply_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    // Migrations to add columns if older tables exist
    try { $pdo->exec("ALTER TABLE contact_messages ADD COLUMN user_id INT UNSIGNED NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE contact_messages ADD CONSTRAINT fk_contact_msg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE contact_replies ADD COLUMN user_id INT UNSIGNED NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE contact_replies ADD CONSTRAINT fk_contact_reply_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL"); } catch (Throwable $e) {}

    // Seed admin if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
    $stmt->execute();
    $count = (int)($stmt->fetch()['c'] ?? 0);
    if ($count === 0) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $insert->execute(['Administrator', 'admin@paradise.local', $adminPassword, 'admin']);
    }

    // Seed rooms if empty, map to existing images in room/ directory
    $stmt = $pdo->query('SELECT COUNT(*) AS c FROM rooms');
    $roomCount = (int)($stmt->fetch()['c'] ?? 0);
    if ($roomCount === 0) {
        $defaultRooms = [
            [
                'name' => 'Ocean View Suite',
                'description' => 'Spacious suite with panoramic ocean views and modern interiors. Booking includes free resort access.',
                'price' => 199.00,
                'capacity' => 3,
                'image' => 'room/90135f04-673a-4420-827b-b156fb0f6348.jpeg',
            ],
            [
                'name' => 'Garden Villa',
                'description' => 'Private villa surrounded by lush gardens, perfect for a tranquil retreat. Free resort access included.',
                'price' => 249.00,
                'capacity' => 4,
                'image' => 'room/c7c6ffa3-ee66-4581-8b53-06ce537d659e.jpeg',
            ],
            [
                'name' => 'Deluxe King Room',
                'description' => 'Modern room with king bed, warm tones, and sleek finishes. Complimentary resort access.',
                'price' => 149.00,
                'capacity' => 2,
                'image' => 'room/4a578ea2-8214-4467-b812-aff3febbebc7.jpeg',
            ],
            [
                'name' => 'Family Suite',
                'description' => 'Comfortable suite ideal for families, featuring extra space and amenities. Free resort access included.',
                'price' => 279.00,
                'capacity' => 5,
                'image' => 'room/01798dce-c932-41c7-893c-25acc70546e8.jpeg',
            ],
        ];

        $insert = $pdo->prepare('INSERT INTO rooms (name, description, price_per_night, capacity, image_path) VALUES (?, ?, ?, ?, ?)');
        foreach ($defaultRooms as $r) {
            $insert->execute([$r['name'], $r['description'], $r['price'], $r['capacity'], $r['image']]);
        }
    }
}

function find_user_by_email(PDO $pdo, string $email): ?array {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function find_user_by_id(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function get_all_active_rooms(PDO $pdo, int $limit = 0): array {
    $sql = 'SELECT * FROM rooms WHERE is_active = 1 ORDER BY created_at DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int)$limit;
    }
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function get_rooms_by_min_capacity(PDO $pdo, int $minGuests, int $limit = 0): array {
    $sql = 'SELECT * FROM rooms WHERE is_active = 1 AND capacity >= ? ORDER BY price_per_night';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int)$limit;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$minGuests]);
    return $stmt->fetchAll();
}

function get_room_by_id(PDO $pdo, int $roomId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();
    return $room ?: null;
}

function is_room_available(PDO $pdo, int $roomId, string $checkIn, string $checkOut): bool {
    // Strict overlap: treat back-to-back as conflict as well
    $sql = "SELECT COUNT(*) FROM bookings b WHERE b.room_id = ? AND b.status IN ('pending','approved','checked_in') AND NOT (b.check_out < DATE(?) OR b.check_in > DATE(?))";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$roomId, $checkIn, $checkOut]);
    $booked = (int)$stmt->fetchColumn();
    if ($booked > 0) return false;
    // also check blocked dates
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM blocked_dates d WHERE d.room_id=? AND NOT (d.end_date < DATE(?) OR d.start_date > DATE(?))");
    $stmt2->execute([$roomId, $checkIn, $checkOut]);
    return (int)$stmt2->fetchColumn() === 0;
}

function get_available_rooms(PDO $pdo, string $checkIn, string $checkOut, int $guests, int $limit = 0): array {
    $sql = "SELECT * FROM rooms r WHERE r.is_active = 1 AND r.capacity >= ?
            AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.room_id = r.id AND b.status IN ('pending','approved','checked_in') AND NOT (b.check_out < DATE(?) OR b.check_in > DATE(?)))
            AND NOT EXISTS (SELECT 1 FROM blocked_dates d WHERE d.room_id = r.id AND NOT (d.end_date < DATE(?) OR d.start_date > DATE(?)))
            ORDER BY r.price_per_night";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int)$limit;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$guests, $checkIn, $checkOut, $checkIn, $checkOut]);
    return $stmt->fetchAll();
}

function create_booking(PDO $pdo, int $userId, int $roomId, string $checkIn, string $checkOut, int $guests): int {
    $stmt = $pdo->prepare('INSERT INTO bookings (user_id, room_id, check_in, check_out, guests) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $roomId, $checkIn, $checkOut, $guests]);
    return (int)$pdo->lastInsertId();
}

function get_user_bookings(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT b.*, r.name AS room_name, r.image_path, (
            SELECT status FROM payments p WHERE p.booking_id=b.id ORDER BY p.created_at DESC LIMIT 1
        ) AS payment_status
        FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.user_id = ? ORDER BY b.created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function ensure_admin(PDO $pdo): void {
    // Helper to re-seed admin if needed
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute(['admin@paradise.local']);
    if (!$stmt->fetch()) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $insert->execute(['Administrator', 'admin@paradise.local', $adminPassword, 'admin']);
    }
}

function get_booking_by_id(PDO $pdo, int $bookingId): ?array {
    $stmt = $pdo->prepare('SELECT b.*, r.name AS room_name, r.image_path, r.price_per_night, r.capacity,
            r.description AS room_description, r.amenities AS room_amenities, r.bed_type AS room_bed_type,
            r.size_sqm AS room_size_sqm, r.view AS room_view
        FROM bookings b JOIN rooms r ON r.id=b.room_id WHERE b.id = ?');
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function set_booking_status(PDO $pdo, int $bookingId, string $status): void {
    $stmt = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?');
    $stmt->execute([$status, $bookingId]);
}

function create_payment(PDO $pdo, int $bookingId, float $amount, string $method, string $status, string $reference): int {
    $stmt = $pdo->prepare('INSERT INTO payments (booking_id, amount, method, status, reference) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$bookingId, $amount, $method, $status, $reference]);
    return (int)$pdo->lastInsertId();
}

function get_latest_payment_for_booking(PDO $pdo, int $bookingId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Pricing helpers
function find_coupon(PDO $pdo, string $code): ?array {
    $code = strtoupper(trim($code));
    if ($code === '') return null;
    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE UPPER(code)=?');
    $stmt->execute([$code]);
    $c = $stmt->fetch();
    if (!$c) return null;
    $today = date('Y-m-d');
    if (($c['valid_from'] && $today < $c['valid_from']) || ($c['valid_to'] && $today > $c['valid_to'])) return null;
    if (!is_null($c['max_uses']) && (int)$c['used_count'] >= (int)$c['max_uses']) return null;
    return $c;
}

function compute_total_with_rules(PDO $pdo, float $basePerNight, string $checkIn, string $checkOut, ?array $coupon = null): array {
    $nights = max(1, (int)round((strtotime($checkOut) - strtotime($checkIn)) / 86400));
    // accumulate multiplier by days in range
    $stmt = $pdo->prepare('SELECT * FROM price_rules WHERE NOT (end_date < ? OR start_date > ?)');
    $stmt->execute([$checkIn, $checkOut]);
    $rules = $stmt->fetchAll();
    $total = 0.0;
    for ($i = 0; $i < $nights; $i++) {
        $d = date('Y-m-d', strtotime($checkIn . " +$i day"));
        $mult = 1.0;
        foreach ($rules as $r) {
            if (!($d < $r['start_date'] || $d > $r['end_date'])) {
                $mult *= (float)$r['multiplier'];
            }
        }
        $total += $basePerNight * $mult;
    }
    $discount = 0.0;
    if ($coupon) {
        if ($coupon['type'] === 'percent') {
            $discount = $total * ((float)$coupon['value'] / 100.0);
        } else {
            $discount = (float)$coupon['value'];
        }
        if ($discount > $total) $discount = $total;
    }
    return ['nights' => $nights, 'subtotal' => round($total, 2), 'discount' => round($discount, 2), 'total' => round($total - $discount, 2)];
}

function increment_coupon_use(PDO $pdo, int $couponId): void {
    $stmt = $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?');
    $stmt->execute([$couponId]);
}

// Audit
function audit_log(PDO $pdo, ?int $userId, string $action, ?string $entity = null, ?int $entityId = null, array $meta = []): void {
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity, entity_id, meta) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $action, $entity, $entityId, json_encode($meta)]);
}

// Reviews
function get_average_rating(PDO $pdo, int $roomId): float {
    $stmt = $pdo->prepare('SELECT AVG(rating) FROM reviews WHERE room_id = ?');
    $stmt->execute([$roomId]);
    return (float)$stmt->fetchColumn();
}

?>


