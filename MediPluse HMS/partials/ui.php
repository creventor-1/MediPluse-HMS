<?php
/**
 * Shared UI Components for MediPulse HMS
 * Implements shadcn-inspired presentation components aligned with UI_Design_Guide.md
 */

require_once __DIR__ . '/icons.php';

function renderPageHeader(string $title, string $eyebrow = '', string $actionHtml = ''): string
{
    $eyebrowHtml = $eyebrow !== '' ? '<p class="eyebrow">' . htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') . '</p>' : '';
    $actionContainer = $actionHtml !== '' ? '<div class="page-header-actions">' . $actionHtml . '</div>' : '';

    return '<div class="page-header"><div>' . $eyebrowHtml . '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2></div>' . $actionContainer . '</div>';
}

function renderStatCard(string $label, string $value, string $iconName = 'activity', string $note = ''): string
{
    $noteHtml = $note !== '' ? '<span class="stat-note">' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</span>' : '';
    $iconSvg = icon($iconName, 'stat-icon-svg', 22);

    return '<div class="stat-card">'
        . '<div class="stat-icon">' . $iconSvg . '</div>'
        . '<div class="stat-content">'
        . '<span class="label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
        . '<strong>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</strong>'
        . $noteHtml
        . '</div></div>';
}

function renderEmptyState(string $title, string $description = '', string $iconName = 'search', string $actionHtml = ''): string
{
    $descHtml = $description !== '' ? '<p class="empty-state-desc">' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>' : '';
    $action = $actionHtml !== '' ? '<div class="empty-state-action">' . $actionHtml . '</div>' : '';

    return '<div class="empty-state">'
        . '<div class="empty-state-icon">' . icon($iconName, 'empty-icon-svg', 36) . '</div>'
        . '<h4>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h4>'
        . $descHtml
        . $action
        . '</div>';
}
