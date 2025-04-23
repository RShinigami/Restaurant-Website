<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
secureSessionStart();

// Redirect non-logged-in users
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle AJAX reservation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_reservation') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!validateCsrfToken($_POST['csrf_token'])) {
        $response['message'] = 'Invalid CSRF token.';
        error_log('CSRF validation failed: ' . print_r($_POST, true));
        echo json_encode($response);
        exit;
    }

    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_SPECIAL_CHARS);
    $time = filter_input(INPUT_POST, 'time', FILTER_SANITIZE_SPECIAL_CHARS);
    $party_size = filter_input(INPUT_POST, 'party_size', FILTER_VALIDATE_INT);
    $table_number = filter_input(INPUT_POST, 'table_number', FILTER_VALIDATE_INT);
    $special_requests = filter_input(INPUT_POST, 'special_requests', FILTER_SANITIZE_SPECIAL_CHARS);

    // Validate inputs
    $errors = [];
    $date_time = DateTime::createFromFormat('Y-m-d h:i A', "$date $time");
    if (!$date_time || $date_time < new DateTime('tomorrow')) {
        $errors[] = 'Invalid or past date/time.';
    }
    if ($party_size === false || $party_size < 1) {
        $errors[] = 'Invalid party size.';
    }

    // Check table existence and capacity
    try {
        $stmt = $db->prepare('SELECT capacity FROM tables WHERE table_number = ?');
        $stmt->execute([$table_number]);
        $table = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$table) {
            $errors[] = 'Invalid table number.';
        } elseif ($party_size > $table['capacity']) {
            $errors[] = 'Party size exceeds table capacity.';
        }
    } catch (PDOException $e) {
        $errors[] = 'Database error checking table: ' . $e->getMessage();
        error_log('Table query error: ' . $e->getMessage());
    }

    // Check for overlapping reservations
    if (empty($errors)) {
        try {
            $duration_hours = $party_size <= 4 ? 1 : 2;
            $start_time = $date_time->format('Y-m-d H:i:s');
            $end_time = (clone $date_time)->modify("+$duration_hours hours")->format('Y-m-d H:i:s');
            $stmt = $db->prepare('
                SELECT COUNT(*) 
                FROM reservations_orders 
                WHERE type = ? 
                AND table_number = ? 
                AND status IN (?, ?) 
                AND (
                    (date_time >= ? AND date_time < ?) 
                    OR 
                    (date_time <= ? AND datetime(date_time, \'+\' || ? || \' hours\') > ?)
                )
            ');
            $stmt->execute([
                'reservation',
                $table_number,
                'pending',
                'confirmed',
                $start_time,
                $end_time,
                $start_time,
                $duration_hours,
                $start_time
            ]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Table is already reserved for this time.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error checking availability: ' . $e->getMessage();
            error_log('Availability query error: ' . $e->getMessage());
        }
    }

    // Insert reservation
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('
                INSERT INTO reservations_orders 
                (customer_id, type, date_time, status, table_number, special_requests) 
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $success = $stmt->execute([
                $_SESSION['customer_id'],
                'reservation',
                $date_time->format('Y-m-d H:i:s'),
                'pending',
                $table_number,
                $special_requests ?: null
            ]);
            if ($success) {
                $response['success'] = true;
                $response['message'] = 'Reservation confirmed! Redirecting to your account...';
            } else {
                $response['message'] = 'Failed to insert reservation.';
                error_log('Insert failed without exception.');
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // SQLite constraint violation
                $response['message'] = 'Table is already reserved at this time.';
            } else {
                $response['message'] = 'Database error: ' . $e->getMessage();
            }
            error_log('Insert error: ' . $e->getMessage() . ' | Data: ' . print_r($_POST, true));
        }
    } else {
        $response['message'] = implode(' ', $errors);
    }

    echo json_encode($response);
    exit;
}

// Generate CSRF token
$csrf_token = generateCsrfToken();

// Operating hours and time slots
$start_time = strtotime('12:00 PM');
$end_time = strtotime('10:00 PM');
$time_slots = [];
for ($time = $start_time; $time <= $end_time; $time += 30 * 60) {
    $time_slots[] = date('h:i A', $time);
}
?>

<?php include '../includes/header.php'; ?>

<main>
<section class="reserve-page">
    <h1>Reserve a Table</h1>

    <!-- Reservation Form -->
    <div class="reservation-form">
        <form id="reservation-form">
            <div class="form-group">
                <label for="reservation-date">Date</label>
                <input type="date" id="reservation-date" name="date" required aria-label="Select reservation date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
            </div>
            <div class="form-group">
                <label for="reservation-time">Time</label>
                <select id="reservation-time" name="time" required aria-label="Select reservation time">
                    <option value="">Select Time</option>
                    <?php foreach ($time_slots as $slot): ?>
                        <option value="<?php echo sanitize($slot); ?>"><?php echo sanitize($slot); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="party-size">Party Size</label>
                <select id="party-size" name="party_size" required aria-label="Select party size">
                    <option value="">Select Party Size</option>
                    <?php
                    $stmt = $db->query('SELECT MAX(capacity) as max_capacity FROM tables');
                    $max_capacity = $stmt->fetch(PDO::FETCH_ASSOC)['max_capacity'] ?: 10;
                    for ($i = 1; $i <= $max_capacity; $i++):
                    ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?> <?php echo $i === 1 ? 'Person' : 'People'; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="table-number">Table</label>
                <select id="table-number" name="table_number" required aria-label="Select table" disabled>
                    <option value="">Select Table</option>
                </select>
                <p id="availability-message" class="availability-message"></p>
                <div id="alternative-times" class="alternative-times"></div>
            </div>
            <div class="form-group">
                <label for="special-requests">Special Requests (Optional)</label>
                <textarea id="special-requests" name="special_requests" rows="4" aria-label="Enter special requests"></textarea>
            </div>
            <button type="button" class="btn" id="reserve-btn">Reserve Table</button>
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
        </form>
    </div>

    <!-- Reservation Confirmation Modal -->
    <div class="modal" id="reservation-modal">
        <div class="modal-content">
            <h2>Confirm Your Reservation</h2>
            <p><strong>Date:</strong> <span id="modal-date"></span></p>
            <p><strong>Time:</strong> <span id="modal-time"></span></p>
            <p><strong>Party Size:</strong> <span id="modal-party-size"></span></p>
            <p><strong>Table:</strong> <span id="modal-table"></span></p>
            <p><strong>Special Requests:</strong> <span id="modal-requests"></span></p>
            <div class="modal-buttons">
                <button type="button" class="btn" id="confirm-reservation-btn">Confirm Reservation</button>
                <button type="button" class="btn" id="cancel-reservation-btn">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>
</section>
</main>

<?php include '../includes/footer.php'; ?>
</body>
</html>