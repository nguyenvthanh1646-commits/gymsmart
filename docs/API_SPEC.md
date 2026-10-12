# API Spec — GymSmart

Tài liệu đặc tả API, dùng làm hợp đồng (contract) giữa backend và frontend. Cập nhật file này ngay khi thêm/sửa endpoint (theo quy định trong `CLAUDE.md`).

> **Lưu ý khi code bằng PHP thuần:** các endpoint dưới đây viết dạng REST để mô tả rõ chức năng và dữ liệu. Khi triển khai, mỗi endpoint là một file trong `public/api/` (ví dụ `POST /auth/login` → `api/login.php`), xác thực bằng **PHP session** (cookie), không dùng JWT. Quy ước response `{success, data, message}` vẫn giữ nguyên.

**Base URL (quy ước mô tả):** `/api`
**Format response chung:** `{ success: boolean, data: object|array|null, message: string }`
**Auth:** PHP session — đăng nhập thành công thì lưu `$_SESSION['user_id']` và `$_SESSION['role']`; mọi endpoint trừ 🔓 Public phải kiểm tra bằng `requireLogin()` / `requireAdmin()` trong `includes/auth.php`.

---

## 1. Auth

| Method | Endpoint | Quyền | Mô tả |
|---|---|---|---|
| POST | `/auth/register` | 🔓 Public | Đăng ký tài khoản mới |
| POST | `/auth/login` | 🔓 Public | Đăng nhập, lưu thông tin vào session |
| GET | `/auth/me` | User | Lấy thông tin tài khoản đang đăng nhập |
| PUT | `/auth/me` | User | Cập nhật hồ sơ (chỉ số cơ thể, mục tiêu...) |

**POST `/auth/register`**
```json
// Request
{
  "fullName": "Nguyen Van A",
  "email": "a@example.com",
  "password": "string"
}
// Response 201
{ "success": true, "data": { "id": 1, "email": "a@example.com" }, "message": "Đăng ký thành công" }
```

**POST `/auth/login`**
```json
// Request
{ "email": "a@example.com", "password": "string" }
// Response 200
{ "success": true, "data": { "user": { "id": 1, "fullName": "...", "role": "USER" } }, "message": "OK" }
```

**PUT `/auth/me`**
```json
// Request
{
  "age": 22, "heightCm": 175, "weightKg": 68,
  "gender": "MALE", "activityLevel": "MODERATE", "goal": "GAIN_MUSCLE"
}
```

---

## 2. Exercise

| Method | Endpoint | Quyền | Mô tả |
|---|---|---|---|
| GET | `/exercises` | User | Danh sách bài tập, filter query param |
| GET | `/exercises/{id}` | User | Chi tiết 1 bài tập |
| POST | `/exercises` | Admin | Tạo bài tập mới |
| PUT | `/exercises/{id}` | Admin | Sửa bài tập |
| DELETE | `/exercises/{id}` | Admin | Xóa bài tập |

**GET `/exercises?type=RESISTANCE&muscleGroup=CHEST&difficulty=BEGINNER`**
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Đẩy ngực tạ đòn", "type": "RESISTANCE", "muscleGroup": "CHEST",
      "difficulty": "BEGINNER", "imageUrl": "...", "videoUrl": "..." }
  ],
  "message": "OK"
}
```

---

## 3. Workout Plan

| Method | Endpoint | Quyền | Mô tả |
|---|---|---|---|
| POST | `/workout-plans/generate` | User | Sinh lịch tập gợi ý theo input |
| GET | `/workout-plans/active` | User | Lấy lịch tập đang active của user |
| GET | `/workout-plans` | User | Lịch sử các lịch tập đã tạo |
| PUT | `/workout-plans/{id}` | User | Sửa lịch tập (đổi tên, trạng thái) |
| POST | `/workout-plans/{id}/exercises` | User | Thêm bài tập vào 1 ngày trong lịch |
| PUT | `/workout-plans/{id}/exercises/{planExerciseId}` | User | Sửa set/rep/thứ tự |
| DELETE | `/workout-plans/{id}/exercises/{planExerciseId}` | User | Xóa bài tập khỏi lịch |

**POST `/workout-plans/generate`**
```json
// Request
{ "daysPerWeek": 4, "experience": "INTERMEDIATE", "goal": "GAIN_MUSCLE" }
// Response 201
{
  "success": true,
  "data": {
    "id": 10, "splitType": "UPPER_LOWER", "daysPerWeek": 4,
    "exercises": [
      { "dayOfWeek": 1, "exerciseId": 3, "exerciseName": "Đẩy ngực tạ đòn", "sets": 4, "reps": 8 }
    ]
  },
  "message": "Tạo lịch tập thành công"
}
```

---

## 4. Workout Log

| Method | Endpoint | Quyền | Mô tả |
|---|---|---|---|
| POST | `/workout-logs` | User | Ghi nhận 1 lần tập (check-in) |
| GET | `/workout-logs?from=&to=` | User | Lịch sử tập theo khoảng ngày |
| DELETE | `/workout-logs/{id}` | User | Xóa 1 log (nhập nhầm) |

**POST `/workout-logs`**
```json
{
  "workoutPlanExerciseId": 22,
  "exerciseId": 3,
  "logDate": "2026-08-28",
  "setsCompleted": 4,
  "repsCompleted": 8,
  "weightKgUsed": 60
}
```

---

## 5. Body Metrics

| Method | Endpoint | Quyền | Mô tả |
|---|---|---|---|
| POST | `/body-metrics` | User | Thêm bản ghi số đo mới |
| GET | `/body-metrics?from=&to=` | User | Lịch sử số đo (vẽ biểu đồ) |
| DELETE | `/body-metrics/{id}` | User | Xóa bản ghi |

```json
// POST /body-metrics
{ "recordedDate": "2026-08-28", "weightKg": 68.5, "bodyFatPercent": 15.2 }
```

---

## 6. Nutrition (TDEE & Macro)

| Method | Endpoint | Quyền | Mô tả |
|---|---|---|---|
| POST | `/nutrition/calculate` | User | Tính TDEE + macro theo chỉ số hiện tại, lưu kết quả |
| GET | `/nutrition/latest` | User | Lấy kết quả tính gần nhất |
| GET | `/nutrition/history` | User | Lịch sử các lần tính (theo dõi thay đổi) |

**POST `/nutrition/calculate`**
```json
// Request (có thể lấy sẵn từ profile user, hoặc cho nhập override)
{ "age": 22, "heightCm": 175, "weightKg": 68, "gender": "MALE", "activityLevel": "MODERATE", "goal": "GAIN_MUSCLE" }
// Response
{
  "success": true,
  "data": { "tdeeKcal": 2450, "targetKcal": 2650, "proteinG": 165, "carbsG": 300, "fatG": 70 },
  "message": "OK"
}
```

---

## 7. Video & hướng dẫn bài tập (nằm trong Exercise)

Không có module bài viết riêng. Ảnh, video và hướng dẫn nằm ngay trong tài nguyên `exercises` (mục 2). Chi tiết 1 bài tập trả thêm các trường:

**GET `/exercises/{id}`**
```json
{
  "success": true,
  "data": {
    "id": 3, "name": "Kéo xà đơn", "type": "RESISTANCE", "muscleGroup": "BACK",
    "difficulty": "INTERMEDIATE",
    "description": "Bài kéo cơ bản cho lưng xô.",
    "instructions": "Bước 1: ...\nBước 2: ...",
    "commonMistakes": "Đu người lấy đà; không xuống hết biên độ.",
    "imageUrl": "...",
    "videoUrl": "dQw4w9WgXcQ"
  },
  "message": "OK"
}
```

- `videoUrl` chỉ lưu **ID video YouTube**; trang web tự ghép thành `https://www.youtube.com/embed/<ID>` khi hiển thị trong `<iframe>`.
- Bài tập chưa có video thì `videoUrl` là `null`, giao diện phải ẩn khung video thay vì báo lỗi.
- Gợi ý bài cùng nhóm cơ: `GET /exercises?muscleGroup=BACK&limit=3` (loại bài hiện tại ở phía PHP).

---

## 8. Chatbot AI

| Method | Endpoint | Quyền | Mô tả |
|---|---|---|---|
| POST | `/chatbot/sessions` | User | Tạo phiên chat mới |
| GET | `/chatbot/sessions` | User | Danh sách phiên chat của user |
| GET | `/chatbot/sessions/{id}/messages` | User | Lịch sử tin nhắn trong phiên |
| POST | `/chatbot/sessions/{id}/messages` | User | Gửi câu hỏi, nhận trả lời từ AI |

**POST `/chatbot/sessions/{id}/messages`**
```json
// Request
{ "content": "Hôm nay tôi nên ăn bao nhiêu protein?" }
// Response
{
  "success": true,
  "data": {
    "userMessage": { "role": "USER", "content": "Hôm nay tôi nên ăn bao nhiêu protein?" },
    "assistantMessage": { "role": "ASSISTANT", "content": "Dựa trên mục tiêu tăng cơ và cân nặng hiện tại, bạn nên nạp khoảng 165g protein/ngày..." }
  },
  "message": "OK"
}
```

> Backend xử lý: lấy `nutrition_targets` gần nhất + `workout_plans` active của user, nhét vào system prompt/context trước khi gọi Anthropic API (phục vụ Mức 2 — cá nhân hóa). Ở Mức 3, backend còn query bảng `exercises` (mô tả, nhóm cơ, hướng dẫn) theo từ khóa trong câu hỏi để đưa vào context và dẫn tới trang bài tập.

---

## Mã lỗi chung (HTTP status)

| Status | Khi nào dùng |
|---|---|
| 200 | Thành công (GET/PUT/DELETE) |
| 201 | Tạo mới thành công (POST) |
| 400 | Request sai định dạng/validate fail |
| 401 | Chưa đăng nhập / token hết hạn |
| 403 | Không đủ quyền (VD: user gọi API admin) |
| 404 | Không tìm thấy resource |
| 500 | Lỗi server |

Response lỗi format thống nhất:
```json
{ "success": false, "data": null, "message": "Email đã được sử dụng" }
```

---

## Ghi chú khi triển khai

- Tất cả endpoint (trừ 🔓 Public) đều phải gọi `requireLogin()` ở đầu file; endpoint Admin gọi `requireAdmin()` — kiểm tra ở PHP, không chỉ ẩn nút ở giao diện
- Endpoint Admin cần thêm `@PreAuthorize("hasRole('ADMIN')")` (hoặc tương đương), không chỉ kiểm tra ở frontend
- Khi thêm entity mới trong `ERD.md`, nhớ bổ sung endpoint tương ứng vào file này
- Với `/nutrition/calculate` và `/workout-plans/generate`: đây là 2 endpoint chứa "thuật toán" quan trọng nhất của đồ án — viết rõ comment giải thích công thức/logic ngay trong code, không chỉ dựa vào tài liệu này
