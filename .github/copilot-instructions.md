# AI Coding Agent Instructions for Course Report Management System

## Project Overview
This is a simple web application for managing educational course reports. It consists of:
- **Backend**: Single PHP API (`api.php`) handling JSON requests/responses
- **Frontend**: Multiple vanilla JavaScript HTML pages (p1.html - p5.html, ui-map.html)
- **Data Storage**: JSON files for courses, templates, and individual reports
- **Deployment**: Runs under XAMPP (localhost) with no build process required

## Architecture & Data Flow
- **Courses**: Stored in `courses.json` as array of `{courseId, course, duration}`
- **Reports**: Individual JSON files in `data/report_{courseId}.json` with normalized structure:
  - `themes`: Array of `{title, duration, references[]}`
  - `questions`: Array of `{question, theme}`
  - `students`: Array of strings
  - `references`: Array of strings
- **Template**: `reportTemplate.json` provides empty structure for new reports
- **API Endpoints**:
  - `GET /api.php?action=getCourses` → Returns courses array
  - `POST /api.php?action=createDraft` → Creates/overwrites report from template
  - `GET /api.php?action=getReport&courseId=X` → Returns existing report or template
  - `POST /api.php?action=saveReport` → Saves normalized report data

## Key Conventions & Patterns
- **File Naming**: Report files use sanitized `courseId` (alphanumeric/_/- only) as `report_{courseId}.json`
- **Data Normalization**: API automatically normalizes report structure on load/save, handling legacy formats
- **UI Input Handling**: References entered as comma-separated text, converted to string arrays
- **State Management**: Current course stored in `localStorage` (courseId, course, duration)
- **Error Handling**: API returns `{"ok": false, "error": "..."}` with HTTP status codes
- **PHP Standards**: Uses `declare(strict_types=1)`, JSON_PRETTY_PRINT, Unicode support

## Development Workflow
- **Setup**: Place in XAMPP htdocs, access via `http://localhost/lab1/`
- **Testing**: Manual browser testing; no automated tests exist
- **Data Persistence**: Reports auto-saved to filesystem; no database
- **Navigation**: ui-map.html serves as central hub linking to edit pages

## Common Patterns
- **API Calls**: Use `fetch()` with JSON headers; check `response.ok` and `j.ok`
- **DOM Manipulation**: Vanilla JS with `$ = id => document.getElementById(id)`
- **Table Rendering**: Dynamic tables for editable lists (themes, questions, etc.)
- **Input Validation**: Trim/filter empty values before saving
- **Debugging**: Each page includes `<pre>` debug output showing current JSON state

## File Structure Reference
- `api.php`: Core API logic with normalization functions
- `p1.html`: Course selection and draft creation
- `ui-map.html`: Navigation hub with report preview
- `p2.html-p5.html`: Section-specific editors (themes/questions/students/references)
- `courses.json`: Static course catalog
- `reportTemplate.json`: Empty report template
- `data/`: Directory for generated report JSON files</content>
<parameter name="filePath">c:\xampp\htdocs\lab1\.github\copilot-instructions.md