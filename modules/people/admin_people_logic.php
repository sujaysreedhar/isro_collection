<?php
require_once __DIR__ . '/PeopleSupport.php';
$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : 'list';
$personId = max(0, (int)($_GET['id'] ?? 0));
$reply = static function (array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

if (in_array($action, ['search_items_ajax', 'link_item_ajax', 'unlink_item_ajax', 'get_linked_items_ajax', 'webhook_get_linked_items_ajax'], true)) {
    try {
        if ($action === 'search_items_ajax') {
            $q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
            if ($q === '') $reply(['data' => []]);
            $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
            $excludeId = max(0, (int)($_GET['exclude_person'] ?? 0));
            $stmt = $this->pdo->prepare("SELECT id, reg_number, title FROM items WHERE (title LIKE ? OR reg_number LIKE ?) AND NOT EXISTS (SELECT 1 FROM item_people ip WHERE ip.item_id = items.id AND ip.person_id = ?) ORDER BY id DESC LIMIT $limit");
            $stmt->execute(['%' . substr($q, 0, 255) . '%', '%' . substr($q, 0, 255) . '%', $excludeId]);
            $reply(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($personId < 1) $reply(['error' => 'Select a valid biography.'], 400);
        $stmt = $this->pdo->prepare('SELECT id FROM people WHERE id = ?');
        $stmt->execute([$personId]);
        if (!$stmt->fetchColumn()) $reply(['error' => 'This biography no longer exists.'], 404);
        if (in_array($action, ['get_linked_items_ajax', 'webhook_get_linked_items_ajax'], true)) {
            $stmt = $this->pdo->prepare('SELECT DISTINCT i.id, i.title, i.reg_number, ip.role FROM items i JOIN item_people ip ON i.id = ip.item_id WHERE ip.person_id = ? ORDER BY i.id DESC');
            $stmt->execute([$personId]);
            $reply(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if (!$isPost) $reply(['error' => 'Use POST to change linked items.'], 405);
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) $reply(['error' => 'Your session expired. Reload this page and try again.'], 403);
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId < 1) $reply(['error' => 'Select a valid collection item.'], 400);
        if ($action === 'link_item_ajax') {
            $stmt = $this->pdo->prepare('SELECT id FROM items WHERE id = ?');
            $stmt->execute([$itemId]);
            if (!$stmt->fetchColumn()) $reply(['error' => 'This collection item no longer exists.'], 404);
            $this->pdo->beginTransaction();
            $this->pdo->prepare('SELECT id FROM people WHERE id = ? FOR UPDATE')->execute([$personId]);
            $stmt = $this->pdo->prepare("INSERT INTO item_people (person_id, item_id, role) SELECT ?, ?, 'Associated' WHERE NOT EXISTS (SELECT 1 FROM item_people WHERE person_id = ? AND item_id = ?)");
            $stmt->execute([$personId, $itemId, $personId, $itemId]);
            $this->pdo->commit();
        } else {
            $this->pdo->prepare('DELETE FROM item_people WHERE person_id = ? AND item_id = ?')->execute([$personId, $itemId]);
        }
        $reply(['success' => true]);
    } catch (Throwable $e) {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
        error_log('Biographies item link: ' . $e->getMessage());
        $reply(['error' => 'Unable to update linked items. Please try again.'], 500);
    }
}

if ($action === 'delete') {
    if (!$isPost) { http_response_code(405); exit('Use the Delete button to remove a biography.'); }
    if ($personId < 1) { http_response_code(400); exit('Select a valid biography.'); }
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Your session expired. Reload the page and try again.'); }
    try {
        $this->pdo->beginTransaction();
        $this->pdo->prepare('DELETE FROM item_people WHERE person_id = ?')->execute([$personId]);
        $this->pdo->prepare('DELETE FROM people WHERE id = ?')->execute([$personId]);
        $this->pdo->commit();
        header('Location: ' . SITE_URL . '/admin/module_page.php?m=people&msg=deleted');
        exit;
    } catch (Throwable $e) {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
        error_log('Biography deletion: ' . $e->getMessage());
        $GLOBALS['people_form_errors'] = ['The profile could not be deleted. Please try again.'];
        $_GET['action'] = 'list';
    }
}

if ($isPost && isset($_POST['save_person'])) {
    [$values, $errors] = PeopleSupport::validate($_POST);
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) $errors[] = 'Your session expired. Reload the page and try again.';
    $GLOBALS['people_form_values'] = $values;
    $GLOBALS['people_form_errors'] = $errors;
    if (!$errors) {
        $profileImage = null;
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM people WHERE slug = ? AND id <> ?');
            $stmt->execute([$values['slug'], $personId]);
            if ($stmt->fetchColumn()) throw new DomainException('That URL slug is already used. Choose a different slug.');
            if ($personId > 0) {
                $stmt = $this->pdo->prepare('SELECT id FROM people WHERE id = ?');
                $stmt->execute([$personId]);
                if (!$stmt->fetchColumn()) throw new DomainException('This biography no longer exists. Return to the list to create a new profile.');
            }
            $profileImage = null;
            if (isset($_FILES['profile_image']) && ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $profileImage = PeopleSupport::storeImage($_FILES['profile_image']);
            $params = [$values['name'], $values['slug'], $values['birth_date'], $values['death_date'], $values['short_description'], $values['biography'], $values['infobox_data'], $values['is_public']];
            if ($personId > 0) {
                $sql = 'UPDATE people SET name = ?, slug = ?, birth_date = ?, death_date = ?, short_description = ?, biography = ?, infobox_data = ?, is_public = ?';
                if ($profileImage !== null || isset($_POST['remove_photo'])) { $sql .= ', profile_image = ?'; $params[] = $profileImage; }
                $params[] = $personId;
                $this->pdo->prepare($sql . ' WHERE id = ?')->execute($params);
            } else {
                $params[] = $profileImage;
                $this->pdo->prepare('INSERT INTO people (name, slug, birth_date, death_date, short_description, biography, infobox_data, is_public, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($params);
                $personId = (int)$this->pdo->lastInsertId();
            }
            header('Location: ' . SITE_URL . '/admin/module_page.php?m=people&action=edit&id=' . $personId . '&msg=saved');
            exit;
        } catch (PDOException $e) {
            PeopleSupport::discardImage($profileImage);
            error_log('Biography save: ' . $e->getMessage());
            $GLOBALS['people_form_errors'] = [($e->getCode() === '23000') ? 'That URL slug is already used. Choose a different slug.' : 'The profile could not be saved. Please try again.'];
        } catch (DomainException | RuntimeException $e) {
            PeopleSupport::discardImage($profileImage);
            $GLOBALS['people_form_errors'] = [$e->getMessage()];
        } catch (Throwable $e) {
            PeopleSupport::discardImage($profileImage);
            error_log('Biography save: ' . $e->getMessage());
            $GLOBALS['people_form_errors'] = ['The profile could not be saved. Please try again.'];
        }
    }
}
