<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Restrict to admins
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    exit('Unauthorized access');
}

// Fetch statistics
// Reservation counts
$stmt = $db->prepare('
    SELECT 
        COUNT(*) as total_reservations,
        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_reservations,
        SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as confirmed_reservations,
        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_reservations
    FROM reservations_orders
    WHERE type = ?
');
$stmt->execute(['reservation']);
$reservation_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Order counts
$stmt = $db->prepare('
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as confirmed_orders,
        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders
    FROM reservations_orders
    WHERE type = ?
');
$stmt->execute(['order']);
$order_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Total revenue
$stmt = $db->prepare('
    SELECT SUM(oi.quantity * m.price) as total_revenue
    FROM order_items oi
    JOIN menu_items m ON oi.menu_id = m.item_id
    JOIN reservations_orders ro ON oi.order_id = ro.id
    WHERE ro.type = ?
');
$stmt->execute(['order']);
$total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0.0;

// Monthly reservations and orders (last 12 months)
$stmt = $db->prepare('
    SELECT 
        strftime("%Y-%m", date_time) as month,
        SUM(CASE WHEN type = "reservation" THEN 1 ELSE 0 END) as reservation_count,
        SUM(CASE WHEN type = "order" THEN 1 ELSE 0 END) as order_count
    FROM reservations_orders
    WHERE date_time >= date("now", "-12 months")
    GROUP BY strftime("%Y-%m", date_time)
    ORDER BY month ASC
');
$stmt->execute();
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 5 ordered items
$stmt = $db->prepare('
    SELECT 
        m.name,
        SUM(oi.quantity) as total_quantity
    FROM order_items oi
    JOIN menu_items m ON oi.menu_id = m.item_id
    JOIN reservations_orders ro ON oi.order_id = ro.id
    WHERE ro.type = ?
    GROUP BY m.item_id, m.name
    ORDER BY total_quantity DESC
    LIMIT 5
');
$stmt->execute(['order']);
$top_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 5 tables
$stmt = $db->prepare('
    SELECT 
        t.table_number,
        t.description,
        COUNT(*) as reservation_count
    FROM reservations_orders ro
    JOIN tables t ON ro.table_number = t.table_number
    WHERE ro.type = ?
    GROUP BY t.table_number, t.description
    ORDER BY reservation_count DESC
    LIMIT 5
');
$stmt->execute(['reservation']);
$top_tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare chart data
$months = [];
$reservations_per_month = [];
$orders_per_month = [];
foreach ($monthly_data as $row) {
    $months[] = $row['month'];
    $reservations_per_month[] = $row['reservation_count'];
    $orders_per_month[] = $row['order_count'];
}

$item_names = array_column($top_items, 'name');
$item_quantities = array_column($top_items, 'total_quantity');

$table_labels = array_map(function($table) {
    return 'Table ' . $table['table_number'] . ($table['description'] ? ' (' . $table['description'] . ')' : '');
}, $top_tables);
$table_counts = array_column($top_tables, 'reservation_count');

$reservation_statuses = [
    'Pending' => $reservation_stats['pending_reservations'],
    'Confirmed' => $reservation_stats['confirmed_reservations'],
    'Cancelled' => $reservation_stats['cancelled_reservations']
];
?>

<div class="stats-container">
    <?php if (!$reservation_stats['total_reservations'] && !$order_stats['total_orders']): ?>
        <p class="no-data animate__animated animate__fadeIn">No activity to display.</p>
    <?php else: ?>
        <!-- Stat Cards -->
        <div class="stat-cards animate__animated animate__fadeInUp">
            <div class="stat-card">
                <i class="fas fa-calendar-check"></i>
                <h3><?php echo sanitize($reservation_stats['total_reservations']); ?></h3>
                <p>Total Reservations</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-shopping-cart"></i>
                <h3><?php echo sanitize($order_stats['total_orders']); ?></h3>
                <p>Total Orders</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>$<?php echo number_format($total_revenue, 2); ?></h3>
                <p>Total Revenue</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-hourglass-half"></i>
                <h3><?php echo sanitize($reservation_stats['pending_reservations']); ?></h3>
                <p>Pending Reservations</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <h3><?php echo sanitize($order_stats['confirmed_orders']); ?></h3>
                <p>Confirmed Orders</p>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-container">
            <div class="chart-card">
                <h3>Activity Per Month (Last 12 Months)</h3>
                <canvas id="activityChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>Reservation Status</h3>
                <canvas id="statusChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>Top 5 Ordered Items</h3>
                <canvas id="itemsChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>Top 5 Reserved Tables</h3>
                <canvas id="tablesChart"></canvas>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .stats-container {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: clamp(1.5rem, 2.5vw, 2rem);
    }

    .no-data {
        text-align: center;
        color: #555;
        font-size: clamp(0.9rem, 2vw, 1.1rem);
        padding: clamp(1rem, 2vw, 1.5rem);
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    .stat-cards {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: clamp(1rem, 2vw, 1.5rem);
        width: 100%;
    }

    .stat-card {
        background: #fff;
        padding: clamp(1rem, 2vw, 1.5rem);
        border-radius: 8px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .stat-card:hover {
        transform: scale(1.05);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .stat-card i {
        color: #a52a2a;
        font-size: clamp(1.5rem, 3vw, 2rem);
        margin-bottom: 0.5rem;
    }

    .stat-card h3 {
        color: #333;
        font-size: clamp(1.2rem, 2.5vw, 1.5rem);
        margin: 0.5rem 0;
        font-weight: 600;
    }

    .stat-card p {
        color: #555;
        font-size: clamp(0.9rem, 2vw, 1.1rem);
        margin: 0;
    }

    .charts-container {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: clamp(1.5rem, 2.5vw, 2rem);
        width: 100%;
    }

    .chart-card {
        background: #fff;
        padding: clamp(1rem, 2vw, 1.5rem);
        border-radius: 8px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        border: 1px solid #e0e0e0;
        transition: transform 0.3s ease;
    }

    .chart-card:hover {
        transform: translateY(-3px);
    }

    .chart-card h3 {
        color: #a52a2a;
        font-size: clamp(1.1rem, 2.2vw, 1.3rem);
        margin-bottom: 1rem;
        text-align: center;
        font-weight: 500;
    }

    .chart-card canvas {
        max-width: 100%;
        max-height: 350px;
        height: clamp(300px, 50vw, 400px);
        margin: 0 auto;
        display: block;
        aspect-ratio: 4 / 3;
    }

    @media (max-width: 768px) {
        .stat-cards {
            grid-template-columns: repeat(2, 1fr);
        }

        .charts-container {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 600px) {
        .stat-cards {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .stat-card {
            padding: clamp(0.8rem, 1.5vw, 1.2rem);
            max-width: 95vw;
            margin: 0 auto;
        }

        .stat-card i {
            font-size: clamp(1.4rem, 2.8vw, 1.8rem);
        }

        .stat-card h3 {
            font-size: clamp(1.1rem, 2.2vw, 1.4rem);
        }

        .stat-card p {
            font-size: clamp(0.85rem, 1.8vw, 1rem);
        }

        .chart-card {
            padding: clamp(0.8rem, 1.5vw, 1.2rem);
            max-width: 95vw;
            margin: 0 auto;
        }

        .chart-card h3 {
            font-size: clamp(1rem, 2vw, 1.2rem);
        }

        .chart-card canvas {
            height: clamp(250px, 60vw, 350px);
            max-height: 350px;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        let charts = []; // Store chart instances

        // Gradient helper function
        const createGradient = (ctx, chartArea, colorStart, colorEnd) => {
            const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
            gradient.addColorStop(0, colorStart);
            gradient.addColorStop(1, colorEnd);
            return gradient;
        };

        // Destroy existing charts to prevent memory leaks
        const destroyCharts = () => {
            charts.forEach(chart => chart.destroy());
            charts = [];
        };

        // Activity Chart (Bar)
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [
                    {
                        label: 'Reservations',
                        data: <?php echo json_encode($reservations_per_month); ?>,
                        borderColor: 'rgba(165, 42, 42, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Orders',
                        data: <?php echo json_encode($orders_per_month); ?>,
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 800,
                    easing: 'easeOutQuad'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Count',
                            color: '#333',
                            font: { size: 14 }
                        },
                        ticks: {
                            precision: 0,
                            font: { size: 12 }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month',
                            color: '#333',
                            font: { size: 14 }
                        },
                        ticks: {
                            font: { size: 12 }
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            font: { size: 14 }
                        }
                    },
                    tooltip: {
                        titleFont: { size: 14 },
                        bodyFont: { size: 12 }
                    }
                }
            }
        });
        charts.push(activityChart);

        // Set gradients for Activity Chart
        activityChart.data.datasets[0].backgroundColor = createGradient(
            activityCtx,
            activityChart.chartArea,
            'rgba(165, 42, 42, 0.7)',
            'rgba(165, 42, 42, 0.3)'
        );
        activityChart.data.datasets[1].backgroundColor = createGradient(
            activityCtx,
            activityChart.chartArea,
            'rgba(40, 167, 69, 0.7)',
            'rgba(40, 167, 69, 0.3)'
        );
        activityChart.update();

        // Reservation Status Chart (Doughnut)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Confirmed', 'Cancelled'],
                datasets: [{
                    data: [
                        <?php echo $reservation_stats['pending_reservations']; ?>,
                        <?php echo $reservation_stats['confirmed_reservations']; ?>,
                        <?php echo $reservation_stats['cancelled_reservations']; ?>
                    ],
                    borderColor: [
                        'rgba(255, 193, 7, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 800,
                    easing: 'easeOutQuad'
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { size: 14 }
                        }
                    },
                    tooltip: {
                        titleFont: { size: 14 },
                        bodyFont: { size: 12 }
                    }
                }
            }
        });
        charts.push(statusChart);

        // Set gradients for Status Chart
        statusChart.data.datasets[0].backgroundColor = [
            createGradient(statusCtx, statusChart.chartArea, 'rgba(255, 193, 7, 0.7)', 'rgba(255, 193, 7, 0.3)'),
            createGradient(statusCtx, statusChart.chartArea, 'rgba(40, 167, 69, 0.7)', 'rgba(40, 167, 69, 0.3)'),
            createGradient(statusCtx, statusChart.chartArea, 'rgba(220, 53, 69, 0.7)', 'rgba(220, 53, 69, 0.3)')
        ];
        statusChart.update();

        // Top Items Chart (Pie)
        const itemsCtx = document.getElementById('itemsChart').getContext('2d');
        const itemsChart = new Chart(itemsCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($item_names); ?>,
                datasets: [{
                    data: <?php echo json_encode($item_quantities); ?>,
                    borderColor: [
                        'rgba(165, 42, 42, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(0, 123, 255, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(111, 66, 193, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 800,
                    easing: 'easeOutQuad'
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { size: 14 }
                        }
                    },
                    tooltip: {
                        titleFont: { size: 14 },
                        bodyFont: { size: 12 }
                    }
                }
            }
        });
        charts.push(itemsChart);

        // Set gradients for Items Chart
        itemsChart.data.datasets[0].backgroundColor = [
            createGradient(itemsCtx, itemsChart.chartArea, 'rgba(165, 42, 42, 0.7)', 'rgba(165, 42, 42, 0.3)'),
            createGradient(itemsCtx, itemsChart.chartArea, 'rgba(40, 167, 69, 0.7)', 'rgba(40, 167, 69, 0.3)'),
            createGradient(itemsCtx, itemsChart.chartArea, 'rgba(0, 123, 255, 0.7)', 'rgba(0, 123, 255, 0.3)'),
            createGradient(itemsCtx, itemsChart.chartArea, 'rgba(255, 193, 7, 0.7)', 'rgba(255, 193, 7, 0.3)'),
            createGradient(itemsCtx, itemsChart.chartArea, 'rgba(111, 66, 193, 0.7)', 'rgba(111, 66, 193, 0.3)')
        ];
        itemsChart.update();

        // Top Tables Chart (Bar)
        const tablesCtx = document.getElementById('tablesChart').getContext('2d');
        const tablesChart = new Chart(tablesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($table_labels); ?>,
                datasets: [{
                    label: 'Reservations',
                    data: <?php echo json_encode($table_counts); ?>,
                    borderColor: 'rgba(165, 42, 42, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 800,
                    easing: 'easeOutQuad'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Reservation Count',
                            color: '#333',
                            font: { size: 14 }
                        },
                        ticks: {
                            precision: 0,
                            font: { size: 12 }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Table',
                            color: '#333',
                            font: { size: 14 }
                        },
                        ticks: {
                            font: { size: 12 }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        titleFont: { size: 14 },
                        bodyFont: { size: 12 }
                    }
                }
            }
        });
        charts.push(tablesChart);

        // Set gradient for Tables Chart
        tablesChart.data.datasets[0].backgroundColor = createGradient(
            tablesCtx,
            tablesChart.chartArea,
            'rgba(165, 42, 42, 0.7)',
            'rgba(165, 42, 42, 0.3)'
        );
        tablesChart.update();

        // Cleanup on page unload
        window.addEventListener('beforeunload', destroyCharts);
    });
</script>