<?php
include 'config.php';

// 檢查連線
if ($conn) {
    echo "Connected to MySQL successfully!<br>";

    // 查詢所有表
    $query = "SHOW TABLES";
    $result = mysqli_query($conn, $query);

    if ($result) {
        echo "Tables in database:<br>";
        while ($row = mysqli_fetch_array($result)) {
            echo $row[0] . "<br>";
        }
    } else {
        echo "Error: " . mysqli_error($conn);
    }

    // 查詢 users 表數據
    $query = "SELECT * FROM users";
    $result = mysqli_query($conn, $query);

    if ($result) {
        echo "<br>Users in database:<br>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "ID: " . $row['user_id'] . ", Name: " . $row['user_name'] . ", Email: " . $row['account'] . "<br>";
        }
    } else {
        echo "Error: " . mysqli_error($conn);
    }

    mysqli_close($conn);
} else {
    echo "Connection failed.";
}
?>