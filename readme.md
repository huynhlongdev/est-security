# Hướng dẫn Bảo mật WordPress

Tài liệu này tổng hợp các biện pháp bảo mật WordPress, bao gồm bảo mật cốt lõi hệ thống/admin, bảo vệ file/database, bảo mật website/frontend, và ghi log/báo cáo.

---

## 1. Bảo mật cốt lõi hệ thống / tài khoản admin

- **Quét username là "admin", đổi username và gửi thông tin về email**  
  → Ngay từ đầu nên đổi username mặc định “admin” để giảm rủi ro brute force.

- **Xử lý strong password & tự động đổi password định kỳ (hàng tháng/3 tháng/6 tháng)**  
  → Bảo vệ mật khẩu admin luôn mạnh và thay đổi định kỳ.

- **Xác thực 2FA**  
  → Bổ sung bảo vệ hai lớp cho admin/ tài khoản quan trọng.

- **Đổi đường dẫn admin, chặn các đường dẫn mặc định**  
  → Ngăn bot quét đường dẫn mặc định như `/wp-admin`, `/wp-login.php`.

- **Giới hạn số lần login sai, khóa IP khi phát hiện spam, mở khóa IP**  
  → Ngăn brute-force attack và spam login.

---

## 2. Bảo mật file & database

- **Random prefix DB**  
  → Tránh việc hacker đoán bảng mặc định `wp_`.

- **Set header chặn request (Security headers)**  
  → Ngăn clickjacking, XSS và các tấn công từ trình duyệt.

- **Check File permissions**  
  → Xác thực quyền file/folder đúng chuẩn (644 cho file, 755 cho folder).

- **Tự động thiết lập permissions cho file `wp-config.php`, `.htaccess`**  
  → File quan trọng được bảo vệ đúng quyền, tránh truy cập trái phép.

- **Tự động cấu hình file `.htaccess` ở root và tự động thêm các file `.htaccess` cho folders `uploads`, `wp-includes`, …**  
  → Giới hạn truy cập trực tiếp, bảo vệ upload, core.

- **Xóa `readme.html`, `license.txt`, `wp-config-sample.php` mặc định và xóa khi update core WordPress**  
  → Ngăn hacker thu thập thông tin version.

- **Tắt Edit file trong admin của WordPress**  
  → Ngăn chèn mã độc trực tiếp trong theme/plugin.

---

## 3. Bảo mật website / frontend

- **Thêm reCAPTCHA vào form login/contact form**  
  → Ngăn spam bot và tấn công brute-force.

- **Chặn chuột phải và copy**  
  → Ngăn người dùng copy nội dung (mức độ bảo vệ thấp, nhưng bổ sung).

- **Chặn iframe**  
  → Ngăn clickjacking và nhúng website trái phép.

- **Quét được các file PHP trong folder `uploads` và xóa nếu nghi ngờ file độc**  
  → Giám sát folder upload thường là nơi hacker chèn backdoor.

---

## 4. Logging & báo cáo

- **Log lại lịch sử kích hoạt/hủy kích hoạt plugin/theme và log login fail**  
  → Giám sát hành vi admin và truy cập trái phép.

- **Tự động quét hằng ngày (cuối ngày) và gửi thông báo qua email để report**  
  → Cập nhật tình trạng bảo mật định kỳ.

- **Tự động logout sau một khoảng thời gian**  
  → Ngăn session bị chiếm đoạt nếu admin quên logout.

---
