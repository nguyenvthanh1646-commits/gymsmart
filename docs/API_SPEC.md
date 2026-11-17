# API Spec — GymSmart

Tài liệu đặc tả API, dùng làm hợp đồng (contract) giữa backend và frontend. Cập nhật file này ngay khi thêm/sửa endpoint (theo quy định trong `CLAUDE.md`).

> **Lưu ý khi code bằng PHP thuần:** từ mục 1 trở đi là bản **thiết kế ban đầu** dạng REST, để mô tả chức năng và dữ liệu. Code thật **không** làm đủ các endpoint đó: nhiều chức năng (đăng nhập, đăng ký, hồ sơ, số đo, TDEE, admin) là **form POST thẳng vào trang `.php`** (có CSRF, Post/Redirect/Get), không qua API. Danh sách endpoint JSON thật nằm ở mục 0 ngay dưới đây.

## 0. Triển khai thực tế (cập nhật 29/10/2026)

Mọi file trong `public/api/` lấy `user_id` từ `$_SESSION`, không bao giờ từ request. API **không** gọi `requireLogin()` (hàm đó chuyển hướng sang HTML) mà tự trả JSON 401. API ghi dữ liệu kiểm tra mã CSRF.

| Method | File | Kiểu trả về | Ghi chú |
|---|---|---|---|
| GET | `/api/body-metrics.php?from=&to=` | `{success, data}` / `{success:false, error}` | Dùng `includes/api.php` → `apiBoot()`. Số đo của user cho Chart.js |
| GET | `/api/nutrition-history.php?from=&to=` | `{success, data}` / `{success:false, error}` | Như trên, lịch sử TDEE/macro |
| POST | `/api/workout-plan-generate.php` | `{success, plan_id}` / `{success:false, message}` | `days_per_week`, `level`, `split_type` (tùy chọn), `csrf_token`. 401/403/409/422/500 |
| POST | `/api/workout-plan-edit.php` | `{success}` / `{success:false, message}` | `action` = update \| replace \| remove \| add, `row_id`, `sets`, `reps`, `new_exercise_id`, `day_of_week`, `exercise_id`, `csrf_token`. Chỉ sửa lịch ACTIVE của chính user |
| POST | `/api/log-workout.php` | `{success}` / `{success:false, message}` | `row_id`, `exercise_id`, `sets`, `reps`, `weight`, `csrf_token`. `row_id` phải thuộc lịch của user |
| POST | `/api/chatbot.php` | `{reply, ...}` / `{error}` | Body JSON `{message, history, mode, task, personalize}`, header `X-CSRF-Token`. Chế độ admin kiểm tra vai trò trong DB |
| GET | `/api/body-metrics.php?client_id=` | như trên | **Phân hệ HLV:** HLV xem số đo của học viên. Người gọi phải là COACH (đọc DB) và có quan hệ ACTIVE với `client_id`, sai thì 404 |
| GET | `/api/coach-messages.php?with=&after=` | `{success, data:{messages}}` / `{success:false, error}` | `apiBoot()`. `with` = id người bên kia; máy chủ tự tìm quan hệ ACTIVE (người gọi là HLV hoặc học viên), không có thì 404. Đánh dấu đã đọc |
| POST | `/api/coach-message-send.php` | `{success, item}` / `{success:false, message}` | `with`, `content` (1-1000 ký tự), `csrf_token`. 400/401/403/404/405/422/429 (gửi < 2 giây hoặc > 60 tin/giờ)/500 |
| GET | `/api/export-workout-plan.php` | file CSV | Lịch ACTIVE của user, có chống CSV injection. Trang (không phải JSON), dùng `requireLogin()` |
| GET | `/api/logout.php` | chuyển hướng `/login.php` | Hủy session |

Khi sửa một file API thì giữ đúng kiểu trả về của file đó, vì JavaScript phía trang đang đọc đúng key đó (`message` hay `error`).

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
  "gender": "MALE", "activityLevel": "MODERATE", "experienceLevel": "BEGINNER", "goal": "GAIN_MUSCLE"
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

> **Triển khai thực tế (A-14, phần biểu đồ Tiến trình):** thêm/xóa số đo làm qua form trong `progress.php` (PRG + CSRF), không qua API. Hai endpoint JSON chỉ đọc, dùng cho Chart.js:
>
> | Method | Endpoint | Ghi chú |
> |---|---|---|
> | GET | `/api/body-metrics.php?from=YYYY-MM-DD&to=YYYY-MM-DD` | `from`/`to` tùy chọn. Chỉ trả dữ liệu của user đang đăng nhập, tối đa 2000 dòng, sắp xếp ngày tăng dần |
> | GET | `/api/nutrition-history.php?from=&to=` | Lịch sử các lần tính TDEE/macro |
>
> ```json
> // GET /api/body-metrics.php
> { "success": true, "data": [ { "id": 5, "date": "2026-09-20", "weightKg": 78.6, "bodyFatPercent": 21.5, "chestCm": null, "waistCm": 87, "armCm": null } ] }
> // GET /api/nutrition-history.php
> { "success": true, "data": [ { "date": "2026-10-13", "tdeeKcal": 2400, "targetKcal": 1920, "proteinG": 125, "carbsG": 200, "fatG": 53 } ] }
> ```
> Lỗi: 401 chưa đăng nhập, 405 không phải GET, 400 `from`/`to` sai định dạng hoặc `from` > `to`, 500 lỗi server (đều trả JSON `{success:false,error}`).

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

## 9. Phân hệ Huấn luyện viên (HLV) — triển khai thật

Phần lớn thao tác là **form POST thẳng vào trang** (có CSRF, Post/Redirect/Get), chỉ nhắn tin và biểu đồ dùng JSON. `user_id` của người thao tác luôn lấy từ session; id trong URL/form chỉ là "muốn tác động ai", máy chủ kiểm tra quan hệ trước khi làm.

| Trang | Ai | Thao tác (POST `action=`) |
|---|---|---|
| `/coach-apply.php` | USER | `submit` (mẫu đơn: headline, specialties[], experience_years, max_clients, certifications, bio, approach, availability, agree_truth, agree_rules), `withdraw` |
| `/coaches.php`, `/my-coach.php` | USER | `request` (coach_id, note), `cancel` (relation_id), `end` (relation_id), `change` (area = PLAN \| NUTRITION, reason) |
| `/admin/coach-applications.php` | ADMIN | `approve` / `reject` (id, note; từ chối bắt buộc có note) |
| `/admin/coaches.php`, `/admin/user-detail.php` | ADMIN | `revoke_coach` (id) |
| `/coach/dashboard.php`, `/coach/clients.php`, `/coach/client.php?id=` | COACH | `accept` / `reject` / `end` (relation_id), `resolve_change` (request_id, decision = approve \| reject, reply), `feedback` (log_date hoặc rỗng, content ≤ 1000) |
| `/coach/client-plan.php?id=` | COACH | `generate` (days_per_week, level, split_type), `adopt`, `update` (row_id, sets, reps), `replace` (row_id, new_exercise_id), `remove` (row_id), `add` (day_of_week, exercise_id, sets, reps) |
| `/coach/client-nutrition.php?id=` | COACH | protein_g, carbs_g, fat_g (máy chủ tính kcal = 4P + 4C + 9F; 1200-5000 kcal; protein 0,8-3,3 g/kg; chất béo ≥ 0,5 g/kg) |
| `/coach/profile.php` | COACH | hồ sơ HLV + `accepting` |

Khi đang có HLV, `POST /api/workout-plan-generate.php` và `/api/workout-plan-edit.php` của học viên trả **403** `{success:false, message:"Lịch tập đang do HLV ... quản lý..."}` (trừ khi HLV đã duyệt yêu cầu sửa còn hạn).

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

- Trang `.php` gọi `requireLogin()` / `requireAdmin()` ở đầu file; file trong `api/` tự kiểm tra session và trả JSON 401. Quyền admin kiểm tra ở PHP, không chỉ ẩn nút ở giao diện
- Khi thêm entity mới trong `ERD.md`, nhớ bổ sung endpoint tương ứng vào file này
- Với `/nutrition/calculate` và `/workout-plans/generate`: đây là 2 endpoint chứa "thuật toán" quan trọng nhất của đồ án — viết rõ comment giải thích công thức/logic ngay trong code, không chỉ dựa vào tài liệu này
