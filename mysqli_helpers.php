<?php
declare(strict_types=1);

/**
 * mysqli_stmt::get_result() requires mysqlnd. These helpers work with or without it.
 */

function db_stmt_fetch_all_assoc(mysqli_stmt $stmt): array
{
    $r = null;
    if (method_exists($stmt, 'get_result')) {
        $r = $stmt->get_result();
    }
    if ($r instanceof mysqli_result) {
        $rows = [];
        while ($row = $r->fetch_assoc()) {
            $rows[] = $row;
        }
        $r->free();
        return $rows;
    }

    $stmt->store_result();
    $meta = $stmt->result_metadata();
    if ($meta === false) {
        return [];
    }
    $fields = $meta->fetch_fields();
    $bind = [];
    $refs = [];
    foreach ($fields as $field) {
        $bind[$field->name] = null;
        $refs[] = &$bind[$field->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $refs);

    $rows = [];
    while ($stmt->fetch()) {
        $copy = [];
        foreach ($bind as $k => $v) {
            $copy[$k] = $v;
        }
        $rows[] = $copy;
    }
    $meta->free();
    return $rows;
}

function db_stmt_fetch_one_assoc(mysqli_stmt $stmt): ?array
{
    $all = db_stmt_fetch_all_assoc($stmt);
    return $all[0] ?? null;
}

/**
 * Build a safe LIKE pattern from user input.
 * Escapes SQL LIKE wildcards (% and _) so the user input is treated literally,
 * then wraps it with % on both sides for partial matching.
 */
function db_like_pattern(string $term): string
{
    $term = trim($term);
    // Escape backslash first, then % and _
    $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    return '%' . $term . '%';
}