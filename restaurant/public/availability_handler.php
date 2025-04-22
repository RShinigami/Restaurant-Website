<?php
// Suppress errors during AJAX to ensure JSON output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';
require_once '../includes/functions.php';
secureSessionStart();

header('Content-Type: application/json');

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_availability') {
    $response = ['success' => false, 'message' => '', 'tables' => [], 'alternatives' => []];

    try {
        // Fetch table capacities from database
        $stmt = $db->query('SELECT table_number, capacity, description FROM tables');
        $table_capacities = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $table_capacities[$row['table_number']] = [
                'capacity' => (int)$row['capacity'],
                'description' => $row['description'] ?: ($row['capacity'] <= 2 ? 'Small' : ($row['capacity'] <= 6 ? 'Medium' : 'Large'))
            ];
        }

        $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_SPECIAL_CHARS);
        $time = filter_input(INPUT_POST, 'time', FILTER_SANITIZE_SPECIAL_CHARS);
        $party_size = filter_input(INPUT_POST, 'party_size', FILTER_VALIDATE_INT);

        // Validate inputs
        if (!$date || !$time || !$party_size) {
            $response['message'] = 'Missing required fields.';
            echo json_encode($response);
            exit;
        }

        $date_time = DateTime::createFromFormat('Y-m-d h:i A', "$date $time");
        if (!$date_time || $date_time < new DateTime('tomorrow')) {
            $response['message'] = 'Reservations must be for tomorrow or later.';
            echo json_encode($response);
            exit;
        }
        if ($party_size < 1 || $party_size > max(array_column($table_capacities, 'capacity'))) {
            $response['message'] = 'Invalid party size.';
            echo json_encode($response);
            exit;
        }

        // Dynamic reservation duration
        $duration_hours = $party_size <= 4 ? 1 : 2; // 1 hour for 1-4, 2 hours for 5+
        $start_time = $date_time->format('Y-m-d H:i:s');
        $end_time = (clone $date_time)->modify("+$duration_hours hours")->format('Y-m-d H:i:s');

        // Check available tables
        $available_tables = [];
        foreach ($table_capacities as $table_number => $table) {
            if ($party_size <= $table['capacity']) {
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
                if ($stmt->fetchColumn() == 0) {
                    $available_tables[] = [
                        'table_number' => $table_number,
                        'capacity' => $table['capacity'],
                        'label' => "Table $table_number (" . $table['description'] . ")"
                    ];
                }
            }
        }

        if (empty($available_tables)) {
            // Suggest alternative times (within same day, 30-min increments)
            $response['message'] = 'No tables available for selected time.';
            $start_hour = new DateTime("$date 12:00 PM");
            $end_hour = new DateTime("$date 10:00 PM");
            $interval = new DateInterval('PT30M');
            $period = new DatePeriod($start_hour, $interval, $end_hour);
            $alternatives = [];

            foreach ($period as $dt) {
                $alt_start = $dt->format('Y-m-d H:i:s');
                $alt_end = (clone $dt)->modify("+$duration_hours hours")->format('Y-m-d H:i:s');
                $alt_tables = [];
                foreach ($table_capacities as $table_number => $table) {
                    if ($party_size <= $table['capacity']) {
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
                            $alt_start,
                            $alt_end,
                            $alt_start,
                            $duration_hours,
                            $alt_start
                        ]);
                        if ($stmt->fetchColumn() == 0) {
                            $alt_tables[] = $table_number;
                        }
                    }
                }
                if (!empty($alt_tables)) {
                    $alternatives[] = [
                        'time' => $dt->format('h:i A'),
                        'tables' => count($alt_tables)
                    ];
                }
            }
            $response['alternatives'] = $alternatives;
        } else {
            $response['success'] = true;
            $response['tables'] = $available_tables;
            $response['message'] = count($available_tables) . ' table(s) available.';
        }
    } catch (Exception $e) {
        $response['message'] = 'Server error: ' . $e->getMessage();
        error_log('Availability Handler Error: ' . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
?>