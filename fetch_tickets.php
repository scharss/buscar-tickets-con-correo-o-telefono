<?php
// Mostrar todos los errores de PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Conexi¨®n a la base de datos
$servername = $_ENV['DB_SERVER'];
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];
$dbname = $_ENV['DB_NAME'];

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    
    if ($type === 'email') {
        $email = $conn->real_escape_string($_POST['email']);
        
        $sql = "SELECT p.ID AS order_id, oim.meta_value AS tickets
                FROM wpyg_posts p
                JOIN wpyg_postmeta pm ON p.ID = pm.post_id
                JOIN wpyg_woocommerce_order_items oi ON p.ID = oi.order_id
                JOIN wpyg_woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                WHERE pm.meta_key = '_billing_email' AND pm.meta_value = '$email' 
                AND p.post_status = 'wc-processing'
                AND oim.meta_key IN ('_lty_lottery_tickets', 'Ticket Number( s )')
                GROUP BY p.ID, oim.meta_value";
    } else if ($type === 'phone') {
        $phone = $conn->real_escape_string($_POST['phone']);
        
        $sql = "SELECT p.ID AS order_id, oim.meta_value AS tickets
                FROM wpyg_posts p
                JOIN wpyg_postmeta pm ON p.ID = pm.post_id
                JOIN wpyg_woocommerce_order_items oi ON p.ID = oi.order_id
                JOIN wpyg_woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                WHERE pm.meta_key = '_billing_phone' AND pm.meta_value = '$phone' 
                AND p.post_status = 'wc-processing'
                AND oim.meta_key IN ('_lty_lottery_tickets', 'Ticket Number( s )')
                GROUP BY p.ID, oim.meta_value";
    }

    $result = $conn->query($sql);

    $orders = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $order_id = $row['order_id'];
            $tickets_raw = $row['tickets'];
            $tickets = @unserialize($tickets_raw);

            if ($tickets === false) {
                $tickets = $tickets_raw;
            } else {
                $tickets = implode(', ', $tickets);
            }

            if (!isset($orders[$order_id])) {
                $orders[$order_id] = [];
            }

            $orders[$order_id][] = $tickets;
        }

        echo '<div class="list-group">';
        foreach ($orders as $order_id => $tickets_array) {
            echo '<div class="list-group-item ticket-list">';
            echo '<h5 class="mb-1">Pedido ID: ' . $order_id . '</h5>';
            echo '<p class="mb-1">Tickets: ' . implode(', ', $tickets_array) . '</p>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning" role="alert">No se encontraron tickets para el ' . ($type === 'email' ? 'correo electr¨®nico' : 'n¨²mero de tel¨¦fono') . ' proporcionado.</div>';
    }
}

$conn->close();
?>
