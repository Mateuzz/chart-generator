<?php

function getDatabase() : mysqli {
    return new mysqli("localhost", "root", "123", "tcc");
}

function selection(mysqli $db, string $query, ?string $paramFormat = null, ...$values) {
    $stmt = $db->prepare($query);
    if ($paramFormat) {
        $stmt->bind_param($paramFormat, ...$values);
    }
    if ($stmt->execute()) 
        return $stmt->get_result();
    return false;
}

function update(mysqli $db, string $query, $paramFormat, $values) {
    $stmt = $db->prepare($query);
    $stmt->bind_param($paramFormat, $values);
    if ($stmt->execute()) {
        return $stmt->num_rows;
    }
    return false;
}

?>
