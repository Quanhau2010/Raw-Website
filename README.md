# HerlysBoard 📋

Web paste code giống Pastebin — mỗi paste lưu thành 1 file JSON riêng, không cần database.

---

## 🚀 Cài đặt

### Yêu cầu
- PHP 8.0+
- Apache/Nginx với quyền ghi file

### Deploy
```
Upload 2 file lên hosting:
├── index.html
├── api.php
└── pastes/        ← tự tạo khi có paste đầu tiên
```

Không cần cài extension, không cần database, chạy ngay.

---

## 📡 API Endpoints

Base URL: `api.php`

### Tạo paste
```
POST api.php?action=create
Content-Type: application/json

{
  "title":    "Tiêu đề",        // không bắt buộc
  "content":  "Nội dung...",    // bắt buộc
  "lang":     "javascript",     // mặc định: plaintext
  "expire":   3600,             // giây, 0 = vĩnh viễn
  "burn":     false,            // xóa sau lần xem đầu
  "password": "matkhau"         // không bắt buộc
}
```
**Response:**
```json
{
  "ok": true,
  "data": {
    "id":   "aBcD1234",
    "file": "pastes/aBcD1234.json",
    "url":  "?id=aBcD1234"
  }
}
```

---

### Xem paste
```
GET api.php?action=get&id=aBcD1234
GET api.php?action=get&id=aBcD1234&password=matkhau
```

---

### Lấy raw text (cho bot fetch)
```
GET api.php?action=raw&id=aBcD1234
```
Trả về `text/plain` — dùng cho Mirai/FCA bot.

---

### Xem file JSON trực tiếp
```
GET pastes/aBcD1234.json
```

---

### Xóa paste
```
DELETE api.php?action=delete&id=aBcD1234
```

---

### Danh sách paste (50 mới nhất)
```
GET api.php?action=list
```

---

### Thống kê
```
GET api.php?action=stats
```
**Response:**
```json
{
  "ok": true,
  "data": { "total": 12, "views": 48 }
}
```

---

## 🤖 Dùng với Mirai Bot

```javascript
// Fetch nội dung paste từ bot
const axios = require('axios');

const res = await axios.get('https://yoursite.com/api.php?action=raw&id=aBcD1234');
const code = res.data; // plain text

// Tạo paste từ bot
const res = await axios.post('https://yoursite.com/api.php?action=create', {
  title:   'Script của bot',
  content: 'console.log("hello")',
  lang:    'javascript',
  expire:  86400,
});
const id = res.data.data.id;
```

---

## 📁 Cấu trúc file JSON

Mỗi paste = 1 file `pastes/{id}.json`:

```json
{
  "id":       "aBcD1234",
  "title":    "My Script",
  "content":  "console.log('hello')",
  "lang":     "javascript",
  "expire":   0,
  "created":  1741660000,
  "views":    3,
  "burn":     false,
  "password": ""
}
```

---

## ✨ Tính năng

- 🔥 **Burn after read** — tự xóa sau lần xem đầu tiên
- 🔒 **Password** — bảo vệ paste bằng mật khẩu
- ⏳ **Hết hạn** — 10 phút / 1 giờ / 1 ngày / 1 tuần / 1 tháng
- 🎨 **Syntax highlight** — 19+ ngôn ngữ
- 📄 **Raw & JSON link** — truy cập trực tiếp
- 📋 **Danh sách & Stats** — quản lý paste

---

## 👤 Tác giả

**Herlys-Bot** — Facebook Bot Developer (Mirai/FCA)
