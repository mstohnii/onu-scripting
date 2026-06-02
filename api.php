<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const DATA_DIR       = __DIR__ . '/data';
const COURSES_FILE   = __DIR__ . '/courses.json';
const TEMPLATE_FILE  = __DIR__ . '/reportTemplate.json';

function respond($data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

function fail(string $message, int $code = 400, array $extra = []): void {
  respond(array_merge(["ok" => false, "error" => $message], $extra), $code);
}

function ensure_data_dir(): void {
  if (!is_dir(DATA_DIR)) {
    if (!mkdir(DATA_DIR, 0777, true) && !is_dir(DATA_DIR)) {
      fail("Cannot create data directory: " . DATA_DIR, 500);
    }
  }
}

function read_json_file(string $path) {
  if (!file_exists($path)) return null;
  $raw = file_get_contents($path);
  if ($raw === false) return null;
  $data = json_decode($raw, true);
  return is_array($data) ? $data : null;
}

function write_json_file(string $path, $data): void {
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) fail("JSON encode failed", 500);
  if (file_put_contents($path, $json) === false) fail("Cannot write file: $path", 500);
}

function get_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false) fail("Cannot read request body", 400);
  $data = json_decode($raw, true);
  if (!is_array($data)) fail("Invalid JSON body", 400);
  return $data;
}

/**
 * Разрешаем только безопасные символы в courseId для имени файла.
 */
function sanitize_course_id(string $courseId): string {
  $courseId = trim($courseId);
  if ($courseId === '') return '';
  // оставить только латиницу/цифры/_/-
  return preg_replace('/[^a-zA-Z0-9_-]+/', '', $courseId) ?? '';
}

function report_path_for(string $courseId): string {
  $safe = sanitize_course_id($courseId);
  if ($safe === '') fail("Missing or invalid courseId", 400);
  return DATA_DIR . "/report_{$safe}.json";
}

/**
 * Нормализация структуры отчёта под ТЗ:
 * themes: [{title, duration, references[]}]
 * questions: [{question, theme}]
 * students: [string]
 * references: [string]
 */
function normalize_report(array $r): array {
  $r['course']    = (string)($r['course'] ?? '');
  $r['courseId']  = (string)($r['courseId'] ?? '');
  $r['duration']  = (string)($r['duration'] ?? '');

  // themes
  $themes = $r['themes'] ?? [];
  if (!is_array($themes)) $themes = [];
  $normThemes = [];
  foreach ($themes as $t) {
    if (is_string($t)) {
      // старый формат (строка) → новый объект
      $title = trim($t);
      if ($title === '') continue;
      $normThemes[] = [
        'title' => $title,
        'duration' => '',
        'references' => []
      ];
      continue;
    }
    if (!is_array($t)) continue;

    $title = (string)($t['title'] ?? '');
    $duration = (string)($t['duration'] ?? '');

    $refs = $t['references'] ?? [];
    if (is_string($refs)) {
      // если вдруг строка: "a, b, c"
      $refs = array_values(array_filter(array_map('trim', explode(',', $refs)), fn($x)=>$x!==''));
    }
    if (!is_array($refs)) $refs = [];
    $refs = array_values(array_filter(array_map('strval', $refs), fn($x)=>trim($x) !== ''));

    if (trim($title) === '') continue;

    $normThemes[] = [
      'title' => $title,
      'duration' => $duration,
      'references' => $refs
    ];
  }
  $r['themes'] = $normThemes;

  // questions
  $qs = $r['questions'] ?? [];
  if (!is_array($qs)) $qs = [];
  $normQs = [];
  foreach ($qs as $q) {
    if (is_string($q)) {
      $txt = trim($q);
      if ($txt === '') continue;
      $normQs[] = ['question' => $txt, 'theme' => ''];
      continue;
    }
    if (!is_array($q)) continue;

    // допускаем, что кто-то мог назвать поле "questions/themes" (как в вашем сообщении)
    $question = (string)($q['question'] ?? ($q['questions'] ?? ''));
    $theme    = (string)($q['theme'] ?? ($q['themes'] ?? ''));

    if (trim($question) === '') continue;

    $normQs[] = ['question' => $question, 'theme' => $theme];
  }
  $r['questions'] = $normQs;

  // students
  $students = $r['students'] ?? [];
  if (!is_array($students)) $students = [];
  $r['students'] = array_values(array_filter(array_map('strval', $students), fn($x)=>trim($x) !== ''));

  // references
  $refs = $r['references'] ?? [];
  if (!is_array($refs)) $refs = [];
  $r['references'] = array_values(array_filter(array_map('strval', $refs), fn($x)=>trim($x) !== ''));

  // grades: [{student, score, note}]
  $grades = $r['grades'] ?? [];
  if (!is_array($grades)) $grades = [];
  $normGrades = [];
  foreach ($grades as $g) {
    if (!is_array($g)) continue;
    $student = trim((string)($g['student'] ?? ''));
    if ($student === '') continue;
    $score = trim((string)($g['score'] ?? ''));
    $note  = (string)($g['note'] ?? '');
    $normGrades[] = [
      'student' => $student,
      'score'   => $score,
      'note'    => $note,
    ];
  }
  $r['grades'] = $normGrades;

  return $r;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

ensure_data_dir();

switch ($action) {

  case 'getCourses': {
    $data = read_json_file(COURSES_FILE);
    if (!is_array($data)) $data = [];
    respond(["ok" => true, "data" => $data]);
  }

  case 'createDraft': {
    if ($method !== 'POST') fail("Method not allowed", 405);

    $body = get_json_body();
    $courseId = (string)($body['courseId'] ?? '');
    $safeId = sanitize_course_id($courseId);
    if ($safeId === '') fail("Missing or invalid courseId", 400);

    $courses = read_json_file(COURSES_FILE);
    if (!is_array($courses)) $courses = [];

    $course = null;
    foreach ($courses as $c) {
      if (is_array($c) && (string)($c['courseId'] ?? '') === $courseId) {
        $course = $c;
        break;
      }
    }
    if (!$course) fail("Course not found: $courseId", 404);

    $path = report_path_for($courseId);
    $tpl = read_json_file($path) ?? read_json_file(TEMPLATE_FILE);
    if (!is_array($tpl)) fail("Missing/invalid template file", 500);

    $tpl['courseId'] = (string)($course['courseId'] ?? '');
    $tpl['course']   = (string)($course['course'] ?? '');
    $tpl['duration'] = (string)($course['duration'] ?? '');

    $tpl = normalize_report($tpl);

    write_json_file($path, $tpl);

    respond(["ok" => true, "data" => $tpl]);
  }

  case 'getReport': {
    $courseId = (string)($_GET['courseId'] ?? '');
    $safeId = sanitize_course_id($courseId);
    if ($safeId === '') fail("Missing or invalid courseId", 400);

    $path = report_path_for($courseId);
    $data = read_json_file($path);

    if (!is_array($data)) {
      // Если файла ещё нет — вернём шаблон (но НЕ создаём автоматически)
      $tpl = read_json_file(TEMPLATE_FILE);
      if (!is_array($tpl)) fail("Missing/invalid template file", 500);

      $tpl['courseId'] = $courseId;
      $tpl['course']   = '';
      $tpl['duration'] = '';
      $tpl = normalize_report($tpl);

      respond(["ok" => true, "data" => $tpl, "note" => "report file not found; returning template"]);
    }

    $data = normalize_report($data);
    respond(["ok" => true, "data" => $data]);
  }

  case 'saveReport': {
    if ($method !== 'POST') fail("Method not allowed", 405);

    $body = get_json_body();
    $courseId = (string)($body['courseId'] ?? '');
    $safeId = sanitize_course_id($courseId);
    if ($safeId === '') fail("Missing or invalid courseId", 400);

    if (!isset($body['report']) || !is_array($body['report'])) {
      fail("Body must contain { courseId, report }", 400);
    }

    $report = normalize_report($body['report']);
    // принудительно закрепим courseId из параметра (чтобы не было рассинхронизации)
    $report['courseId'] = $courseId;

    $path = report_path_for($courseId);
    write_json_file($path, $report);

    respond(["ok" => true, "data" => $report]);
  }

  default:
    fail("Unknown action: $action", 404);
}
