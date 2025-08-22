Got it — no emojis. Here’s a full `README.md` for your `tests/` folder, replacing the old `tests/readme` with clear step-by-step API test instructions.

````md
# DrawNext API Test Guide

This document provides reproducible examples for testing the DrawNext API endpoints from **Windows** using `curl.exe`.  
These tests are useful for verifying uploads, submission flows, and backend behavior.

---

## 1. Legacy Single-Shot Submission

Uploads the drawing **and** metadata in a single request to `/api/drawings/create.php`.

```powershell
# Legacy single-shot submission (one-step upload)
curl.exe -sS -X POST --ssl-no-revoke `
  -F "email=trpsimoes@gmail.com" `
  -F "notebook_id=1" `
  -F "section_id=1" `
  -F "page=1" `
  -F "drawing=@tests/files/drawing.jpg;type=image/jpeg" `
  "https://drawnext.ddev.site/backend/api/drawings/create.php"
````

### Notes

* `drawing=@...` tells `curl` to upload the file.
* Metadata (`email`, `notebook_id`, etc.) are included in the same multipart form.
* This mode is supported for backward compatibility but is being replaced by the two-step submission process.

---

## 2. Two-Step Submission (Recommended)

This is the current, preferred workflow.
Step A uploads the file to a temporary location.
Step B finalizes the submission with metadata and the `uploadToken`.

### Step A – Temporary Upload

```powershell
# Step A: Upload image to /api/images/temp.php
curl.exe -sS -X POST --ssl-no-revoke `
  -F "image=@tests/files/drawing.jpg;type=image/jpeg" `
  "https://drawnext.ddev.site/backend/api/images/temp.php"
```

**Example response:**

```json
{
  "success": true,
  "uploadToken": "6722d0e9e74a62ce69658a006a4af055",
  "imageMeta": {
    "width": 1200,
    "height": 900,
    "hash": "b665f92e985ede946eb95b740f8f340b0509a2d0"
  }
}
```

* The server writes both a `.jpg` file and a `.json` sidecar file in the temporary uploads folder.
* Copy the `uploadToken` from this response for the next step.

---

### Step B – Finalize Submission

```powershell
# Step B: Finalize by posting metadata + uploadToken to /api/drawings/create.php
curl.exe -sS -X POST --ssl-no-revoke `
  -H "Content-Type: application/json" `
  -d "{ \"input\": {
          \"drawing\": { \"upload_token\": \"6722d0e9e74a62ce69658a006a4af055\" },
          \"email\": \"trpsimoes@gmail.com\",
          \"notebook_id\": 1,
          \"section_id\": 1,
          \"page\": 1
       } }" `
  "https://drawnext.ddev.site/backend/api/drawings/create.php"
```

**Example response:**

```json
{
  "success": true,
  "drawing_id": 42,
  "message": "Drawing successfully submitted."
}
```

* Replace the `upload_token` with the one received in Step A.
* Only metadata is submitted here — the image is not re-uploaded.
* On success, the image is moved out of the temporary uploads area, optimized, and associated with the drawing in the database.

---

## Summary

* **Single-Shot Submission**: Uploads file and metadata in one request. Simple, but less reliable for large files or flaky connections.
* **Two-Step Submission** (recommended): Separates the file upload and metadata submission, improving reliability and efficiency. This is the flow used by the React frontend.

```

Do you want me to also include the **expected error responses** (for example, `409` for slot already taken, `422` for validation errors) in this README, so testers can compare them directly?
```
