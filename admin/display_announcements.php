<?php
// Include the DB connection from your Docker setup
require_once __DIR__ . '/../connect.php'; // if connect.php is one level up


// Fetch announcements ordered by timestamp descending
$sql = 'SELECT title, context, target_audience, priority, timestamp FROM announcements ORDER BY timestamp DESC';
$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$announcements = $result->fetch_all(MYSQLI_ASSOC);

$result->free();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Announcements Display</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <style>
        @font-face {
            font-family: 'Gudea';
            src: url('Fonts/Gudea-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Open Sans';
            src: url('Fonts/OpenSans-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #ffffff;
            margin: 2rem 1rem;
            color: #000;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Gudea', sans-serif;
            font-weight: normal;
        }

        .announcement-box {
            max-width: 800px;
            margin: 0 auto;
            border-radius: 12px;
            border: 1px solid #ddd;
            box-shadow: 0 4px 12px rgb(0 0 0 / 0.08);
            padding: 0;
            background: #fff;
        }

        .title-bar {
            background-color: #81869E;
            color: #fff;
            border-radius: 12px 12px 0 0;
            padding: 1rem 1.5rem;
            text-align: center;
            font-size: 1.8rem;
            font-weight: normal;
            font-family: 'Gudea', sans-serif;
        }

        .announcement {
            position: relative;
            border-bottom: 1px solid #eee;
            padding: 1.5rem 1.5rem 1.5rem 1.5rem;
        }
        .announcement:last-child {
            border-bottom: none;
        }

        .announcement h2 {
            font-size: 1.5rem;
            margin-bottom: 0.3rem;
            color: #000;
        }
        .announcement p {
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }
        .timestamp {
            font-size: 0.85rem;
            color: #666;
        }

        .priority-btn {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            border: none;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
            font-weight: bold;
            color: #fff;
            cursor: default;
            user-select: none;
        }
        .priority-high {
            background-color: #d9534f; /* red */
        }
        .priority-medium {
            background-color: #f0ad4e; /* orange */
        }
        .priority-low {
            background-color: #5bc0de; /* blue */
        }
    </style>
</head>
<body>

<div class="announcement-box">
    <div class="title-bar">Announcements</div>

    <?php foreach($announcements as $a): ?>
    <div class="announcement">
        <h2><?php echo htmlspecialchars($a['title']); ?></h2>
        <p><?php echo nl2br(htmlspecialchars($a['context'])); ?></p>
        <p><strong>Target Audience:</strong> <?php echo htmlspecialchars($a['target_audience']); ?></p>

        <?php
            $priorityClass = '';
            $priorityText = htmlspecialchars($a['priority']);
            switch(strtolower($a['priority'])) {
                case 'high': $priorityClass = 'priority-high'; break;
                case 'medium': $priorityClass = 'priority-medium'; break;
                case 'low': $priorityClass = 'priority-low'; break;
                default: $priorityClass = 'priority-low';
            }
        ?>
        <button class="priority-btn <?php echo $priorityClass; ?>"><?php echo $priorityText; ?></button>

        <p class="timestamp">Posted: <?php echo htmlspecialchars($a['timestamp']); ?></p>
    </div>
    <?php endforeach; ?>

</div>

</body>
</html>
