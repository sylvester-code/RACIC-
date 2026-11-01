<?php
// calendar/events_feed.php - Returns JSON events feed for FullCalendar
require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode([]);
    exit;
}

$db = getDB();

$events = [];

// 1. Fetch Calendar Events
$stmt = $db->query("
    SELECT e.id, e.title, e.start_datetime as start, e.end_datetime as end,
           e.color, e.venue, e.description, e.event_type, d.name as department_name
    FROM events e
    LEFT JOIN departments d ON d.id = e.department_id
");
$rawEvents = $stmt->fetchAll();

foreach ($rawEvents as $ev) {
    $events[] = [
        'id' => 'ev_' . $ev['id'],
        'title' => $ev['title'],
        'start' => $ev['start'],
        'end' => $ev['end'],
        'backgroundColor' => $ev['color'] ?: '#0f2c59',
        'borderColor' => $ev['color'] ?: '#0f2c59',
        'textColor' => '#ffffff',
        'extendedProps' => [
            'type' => $ev['event_type'],
            'venue' => $ev['venue'],
            'department' => $ev['department_name'] ?: 'Church Wide',
            'description' => $ev['description']
        ]
    ];
}

// 2. Fetch Activities as Calendar Items
$actStmt = $db->query("
    SELECT a.id, a.activity_code, a.title, a.activity_date, a.start_time, a.end_time,
           a.venue, a.status, d.name as department_name
    FROM activities a
    LEFT JOIN departments d ON d.id = a.department_id
");
$rawActs = $actStmt->fetchAll();

foreach ($rawActs as $act) {
    $start = $act['activity_date'] . 'T' . $act['start_time'];
    $end = $act['activity_date'] . 'T' . $act['end_time'];
    $color = match($act['status']) {
        'Completed' => '#198754',
        'In Progress' => '#fd7e14',
        'Cancelled' => '#dc3545',
        default => '#6f42c1'
    };

    $events[] = [
        'id' => 'act_' . $act['id'],
        'title' => '[' . $act['activity_code'] . '] ' . $act['title'],
        'start' => $start,
        'end' => $end,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => '#ffffff',
        'url' => BASE_URL . 'activities/view.php?id=' . $act['id'],
        'extendedProps' => [
            'type' => 'Activity Program',
            'venue' => $act['venue'],
            'department' => $act['department_name'] ?: 'Departmental',
            'description' => 'Status: ' . $act['status']
        ]
    ];
}

echo json_encode($events);
exit;

