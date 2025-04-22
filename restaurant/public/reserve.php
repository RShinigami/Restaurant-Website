<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
secureSessionStart();

// Redirect non-logged-in users
if (!isLoggedIn()) {
    header('Location: login.php');
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
            <form id="confirm-reservation-form" method="POST" action="reserve.php">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                <input type="hidden" name="date" id="confirm-date">
                <input type="hidden" name="time" id="confirm-time">
                <input type="hidden" name="party_size" id="confirm-party-size">
                <input type="hidden" name="table_number" id="confirm-table-number">
                <input type="hidden" name="special_requests" id="confirm-special-requests">
                <button type="submit" class="btn" name="submit_reservation">Confirm Reservation</button>
                <button type="button" class="btn" id="cancel-reservation-btn">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reservation-form');
    const dateInput = document.getElementById('reservation-date');
    const timeSelect = document.getElementById('reservation-time');
    const partySizeSelect = document.getElementById('party-size');
    const tableSelect = document.getElementById('table-number');
    const availabilityMessage = document.getElementById('availability-message');
    const alternativeTimes = document.getElementById('alternative-times');
    const reserveBtn = document.getElementById('reserve-btn');
    const modal = document.getElementById('reservation-modal');
    const confirmForm = document.getElementById('confirm-reservation-form');
    const cancelBtn = document.getElementById('cancel-reservation-btn');

    // Check availability on input change
    const checkAvailability = () => {
        const date = dateInput.value;
        const time = timeSelect.value;
        const partySize = partySizeSelect.value;

        if (date && time && partySize) {
            fetch('/public/availability_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=check_availability&date=${encodeURIComponent(date)}&time=${encodeURIComponent(time)}&party_size=${encodeURIComponent(partySize)}`
            })
            .then(response => response.json())
            .then(data => {
                tableSelect.innerHTML = '<option value="">Select Table</option>';
                tableSelect.disabled = true;
                availabilityMessage.textContent = data.message;
                alternativeTimes.innerHTML = '';

                if (data.success && data.tables && data.tables.length > 0) {
                    data.tables.forEach(table => {
                        const option = document.createElement('option');
                        option.value = table.table_number;
                        option.textContent = table.label;
                        tableSelect.appendChild(option);
                    });
                    tableSelect.disabled = false;
                    availabilityMessage.classList.add('success');
                    availabilityMessage.classList.remove('error');
                } else {
                    availabilityMessage.classList.add('error');
                    availabilityMessage.classList.remove('success');
                    if (data.alternatives && data.alternatives.length > 0) {
                        const altList = document.createElement('ul');
                        altList.innerHTML = '<strong>Try these times:</strong>';
                        data.alternatives.forEach(alt => {
                            const li = document.createElement('li');
                            li.textContent = `${alt.time} (${alt.tables} table${alt.tables > 1 ? 's' : ''} available)`;
                            li.style.cursor = 'pointer';
                            li.addEventListener('click', () => {
                                timeSelect.value = alt.time;
                                checkAvailability();
                            });
                            altList.appendChild(li);
                        });
                        alternativeTimes.appendChild(altList);
                    }
                }
            })
            .catch(error => {
                availabilityMessage.textContent = 'Error checking availability. Please try again.';
                availabilityMessage.classList.add('error');
                console.error('Fetch error:', error);
                showToast('Failed to check availability.', 'error');
            });
        }
    };

    dateInput.addEventListener('change', checkAvailability);
    timeSelect.addEventListener('change', checkAvailability);
    partySizeSelect.addEventListener('change', checkAvailability);

    // Handle reserve button click
    reserveBtn.addEventListener('click', () => {
        if (!form.checkValidity()) {
            showToast('Please fill all required fields.', 'error');
            return;
        }
        if (tableSelect.value === '') {
            showToast('Please select an available table.', 'error');
            return;
        }

        document.getElementById('modal-date').textContent = dateInput.value;
        document.getElementById('modal-time').textContent = timeSelect.value;
        document.getElementById('modal-party-size').textContent = partySizeSelect.value;
        document.getElementById('modal-table').textContent = tableSelect.options[tableSelect.selectedIndex].text;
        document.getElementById('modal-requests').textContent = document.getElementById('special-requests').value || 'None';

        document.getElementById('confirm-date').value = dateInput.value;
        document.getElementById('confirm-time').value = timeSelect.value;
        document.getElementById('confirm-party-size').value = partySizeSelect.value;
        document.getElementById('confirm-table-number').value = tableSelect.value;
        document.getElementById('confirm-special-requests').value = document.getElementById('special-requests').value;

        modal.classList.add('active');
    });

    // Handle modal interactions
    cancelBtn.addEventListener('click', () => modal.classList.remove('active'));
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('active');
    });
});

// Toast notification
function showToast(message, type) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type} active`;
    setTimeout(() => { toast.className = 'toast'; }, 3000);
}
</script>

<style>
.alternative-times {
    margin-top: 0.5rem;
    color: #333;
}
.alternative-times ul {
    list-style: none;
    padding: 0;
}
.alternative-times li {
    margin: 0.3rem 0;
    color: #a52a2a;
    cursor: pointer;
}
.alternative-times li:hover {
    text-decoration: underline;
}
</style>

<?php
// Handle reservation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reservation'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        die('Invalid CSRF token.');
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
    if ($party_size < 1) {
        $errors[] = 'Invalid party size.';
    }

    // Check table existence and capacity
    $stmt = $db->prepare('SELECT capacity FROM tables WHERE table_number = ?');
    $stmt->execute([$table_number]);
    $table = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$table) {
        $errors[] = 'Invalid table number.';
    } elseif ($party_size > $table['capacity']) {
        $errors[] = 'Party size exceeds table capacity.';
    }

    // Check for overlapping reservations
    if (empty($errors)) {
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
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare('
                INSERT INTO reservations_orders 
                (customer_id, type, date_time, status, table_number, special_requests) 
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $_SESSION['customer_id'],
                'reservation',
                $date_time->format('Y-m-d H:i:s'),
                'pending',
                $table_number,
                $special_requests ?: null
            ]);
            echo '<script>showToast("Reservation confirmed!", "success"); setTimeout(() => location.reload(), 2000);</script>';
        } catch (PDOException $e) {
            $errors[] = 'Failed to make reservation: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $error_message = implode(' ', $errors);
        echo '<script>showToast("' . addslashes($error_message) . '", "error");</script>';
    }
}
?>

<?php include '../includes/footer.php'; ?>
</main>
</body>
</html>