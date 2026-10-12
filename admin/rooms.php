<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils.php';
require_admin();
$pdo = get_pdo();

// Helper: upload image to /room and return relative path or null
function upload_room_image_if_any(array $fileInput): ?string {
    if (!isset($fileInput['error']) || (int)$fileInput['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int)$fileInput['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $tmpPath = $fileInput['tmp_name'] ?? '';
    if (!$tmpPath || !is_uploaded_file($tmpPath)) {
        return null;
    }

    // Determine extension safely
    $allowed = ['jpg','jpeg','png','gif'];
    $originalName = (string)($fileInput['name'] ?? 'upload');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        $ext = 'jpg';
    }

    $destDir = __DIR__ . '/../room';
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0777, true);
    }
    try {
        $random = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        $random = (string)time();
    }
    $fileName = $random . '.' . $ext;
    $destAbs = $destDir . '/' . $fileName;
    if (!@move_uploaded_file($tmpPath, $destAbs)) {
        return null;
    }
    return 'room/' . $fileName;
}

// Helper: list existing images under /room for picker
// (Folder image picker removed per request)

// Handle create/update/toggle/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $capacity = (int)($_POST['capacity'] ?? 2);
        $image = trim($_POST['image_path'] ?? '');
        $uploaded = upload_room_image_if_any($_FILES['image_file'] ?? []);
        if ($uploaded) { $image = $uploaded; }
        $bed = trim($_POST['bed_type'] ?? '');
        $size = (int)($_POST['size_sqm'] ?? 0);
        $view = trim($_POST['view'] ?? '');
        $amenities = trim($_POST['amenities'] ?? '');
        if ($name && $desc && $price > 0 && $capacity > 0 && $image) {
            $stmt = $pdo->prepare('INSERT INTO rooms (name, description, price_per_night, capacity, image_path, bed_type, size_sqm, view, amenities) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $desc, $price, $capacity, $image, $bed, $size, $view, $amenities]);
            set_flash('success', 'Room created.');
        } else {
            set_flash('danger', 'Please complete all fields correctly.');
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE rooms SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([$id]);
        set_flash('success', 'Room status updated.');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM rooms WHERE id = ?');
        $stmt->execute([$id]);
        set_flash('success', 'Room deleted.');
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $capacity = (int)($_POST['capacity'] ?? 2);
        $image = trim($_POST['image_path'] ?? '');
        $uploaded = upload_room_image_if_any($_FILES['image_file'] ?? []);
        if ($uploaded) { $image = $uploaded; }
        $bed = trim($_POST['bed_type'] ?? '');
        $size = (int)($_POST['size_sqm'] ?? 0);
        $view = trim($_POST['view'] ?? '');
        $amenities = trim($_POST['amenities'] ?? '');
        if ($id && $name && $desc && $price > 0 && $capacity > 0 && $image) {
            $stmt = $pdo->prepare('UPDATE rooms SET name=?, description=?, price_per_night=?, capacity=?, image_path=?, bed_type=?, size_sqm=?, view=?, amenities=? WHERE id=?');
            $stmt->execute([$name, $desc, $price, $capacity, $image, $bed, $size, $view, $amenities, $id]);
            set_flash('success', 'Room updated.');
        } else {
            set_flash('danger', 'Please complete all fields correctly.');
        }
    }
}

$rooms = $pdo->query('SELECT * FROM rooms ORDER BY created_at DESC LIMIT 9')->fetchAll();

include __DIR__ . '/../partials/header.php';
?>

<div class="container mt-5 pt-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="section-heading mb-0">Manage Rooms</h2>
  </div>
  <div class="card room-card p-3 mb-4">
    <form id="create-room-form" class="row g-3" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="create">
      <div class="col-12 col-md-4">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" required>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Price per night (₱)</label>
        <input type="number" step="0.01" min="0" name="price" class="form-control" required>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Capacity</label>
        <input type="number" min="1" name="capacity" class="form-control" required>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label">Image file</label>
        <input type="file" name="image_file" accept="image/*" class="form-control" required>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Bed type</label>
        <input type="text" name="bed_type" class="form-control" placeholder="King / Twin">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Room size (sqm)</label>
        <input type="number" min="0" name="size_sqm" class="form-control" placeholder="22">
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label">View</label>
        <input type="text" name="view" class="form-control" placeholder="Ocean / Garden / City">
      </div>
      <div class="col-12">
        <label class="form-label">Amenities (comma separated)</label>
        <input type="text" name="amenities" class="form-control" placeholder="WiFi, Pool, Breakfast, AC">
      </div>
      <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3" required></textarea>
      </div>
      <div class="col-12">
        <button class="btn btn-primary" type="submit">Add Room</button>
      </div>
    </form>
  </div>

  <div class="row g-3">
    <?php foreach ($rooms as $room): ?>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card room-card h-100">
          <?php $resolvedImg = resolve_image_path((string)$room['image_path']); ?>
          <img src="/paradiseresort/<?= htmlspecialchars($resolvedImg) ?>" onerror="this.onerror=null;this.src='/paradiseresort/images/banner-item1.jpg'" class="card-img-top object-fit-cover js-viewable" data-full="/paradiseresort/<?= htmlspecialchars($resolvedImg) ?>" style="height: 200px;" alt="room">
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h5 class="card-title mb-0"><?= htmlspecialchars($room['name']) ?></h5>
              <span class="badge <?= $room['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $room['is_active'] ? 'Active' : 'Inactive' ?></span>
            </div>
            <div class="small text-muted mb-2">Capacity: <?= (int)$room['capacity'] ?> • <span class="price">₱<?= number_format((float)$room['price_per_night'],2) ?>/night</span></div>
            <div class="small text-muted mb-2">Bed: <?= htmlspecialchars($room['bed_type'] ?? '—') ?> • Size: <?= (int)($room['size_sqm'] ?? 0) ?>sqm • View: <?= htmlspecialchars($room['view'] ?? '—') ?></div>
            <p class="card-text flex-grow-1"><?= htmlspecialchars($room['description']) ?></p>
            <form method="post" class="d-grid gap-2">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$room['id'] ?>">
              <button class="btn btn-outline-light w-100" type="submit">Toggle Active</button>
            </form>
            <button class="btn btn-primary w-100 mt-2" data-bs-toggle="collapse" data-bs-target="#edit-<?= (int)$room['id'] ?>">Edit</button>
            <form method="post" onsubmit="return confirm('Delete this room?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$room['id'] ?>">
              <button class="btn btn-outline-light w-100 mt-2" type="submit">Delete</button>
            </form>
          </div>
          <div class="collapse border-top" id="edit-<?= (int)$room['id'] ?>">
            <div class="p-3">
              <form method="post" enctype="multipart/form-data" class="row g-2">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$room['id'] ?>">
                <input type="hidden" name="image_path" value="<?= htmlspecialchars($room['image_path']) ?>">
                <div class="col-12">
                  <label class="form-label">Name</label>
                  <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($room['name']) ?>" required>
                </div>
                <div class="col-6">
                  <label class="form-label">Price</label>
                  <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= (float)$room['price_per_night'] ?>" required>
                </div>
                <div class="col-6">
                  <label class="form-label">Capacity</label>
                  <input type="number" min="1" name="capacity" class="form-control" value="<?= (int)$room['capacity'] ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Replace image (optional)</label>
                  <input type="file" name="image_file" accept="image/*" class="form-control">
                  <div class="form-text">Current: <code>/<?= htmlspecialchars($room['image_path']) ?></code></div>
                </div>
                <div class="col-6">
                  <label class="form-label">Bed type</label>
                  <input type="text" name="bed_type" class="form-control" value="<?= htmlspecialchars($room['bed_type']) ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">Room size (sqm)</label>
                  <input type="number" min="0" name="size_sqm" class="form-control" value="<?= (int)($room['size_sqm'] ?? 0) ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">View</label>
                  <input type="text" name="view" class="form-control" value="<?= htmlspecialchars($room['view']) ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">Amenities (comma separated)</label>
                  <input type="text" name="amenities" class="form-control" value="<?= htmlspecialchars($room['amenities']) ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">Description</label>
                  <textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($room['description']) ?></textarea>
                </div>
                <div class="col-12"><button class="btn btn-primary" type="submit">Save Changes</button></div>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Folder picker and previews removed per request -->

<?php include __DIR__ . '/../partials/footer.php'; ?>


