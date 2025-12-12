# WordPress Security Guidelines

This document outlines a comprehensive set of security measures for WordPress, covering core system/admin security, file/database protection, frontend security, and logging/reporting.

---

## 1. Core System / Admin Security

- **Scan for "admin" username, rename it, and send notification via email**  
  → Change the default "admin" username early to reduce brute-force risks.

- **Enforce strong passwords & automatically rotate passwords periodically (monthly/3 months/6 months)**  
  → Ensure admin passwords are always strong and changed regularly.

- **Enable Two-Factor Authentication (2FA)**  
  → Adds an extra layer of protection for admin and important accounts.

- **Change admin URL and block default paths**  
  → Prevent bots from targeting default paths like `/wp-admin` or `/wp-login.php`.

- **Limit login attempts, lock IPs on spam detection, and allow unlocking**  
  → Prevent brute-force attacks and login spam.

---

## 2. File & Database Security

- **Randomize database table prefixes**  
  → Prevent hackers from guessing default tables like `wp_`.

- **Set security headers**  
  → Prevent clickjacking, XSS, and other browser-based attacks.

- **Check file permissions**  
  → Ensure correct permissions for files/folders (644 for files, 755 for folders).

- **Automatically set permissions for `wp-config.php` and `.htaccess`**  
  → Protect critical files from unauthorized access.

- **Automatically configure `.htaccess` in root and for folders like `uploads`, `wp-includes`**  
  → Restrict direct access and protect sensitive folders.

- **Remove default files like `readme.html`, `license.txt`, `wp-config-sample.php` and ensure deletion after core updates**  
  → Prevent hackers from collecting WordPress version information.

- **Disable file editing in the WordPress admin**  
  → Prevent inserting malicious code via theme/plugin editor.

---

## 3. Website / Frontend Security

- **Add reCAPTCHA to login/contact forms**  
  → Prevent spam bots and brute-force attacks.

- **Disable right-click and copy**  
  → Protect content from casual copying (low-level protection).

- **Block iframes**  
  → Prevent clickjacking and unauthorized embedding of the website.

- **Scan PHP files in `uploads` folder and remove suspicious files**  
  → Monitor the uploads folder, a common location for hacker backdoors.

---

## 4. Logging & Reporting

- **Log all plugin/theme activations, deactivations**  
  → Monitor admin activities and potential unauthorized access attempts.

- **Daily automatic scans and email reporting**  
  → Keep track of security status on a regular basis.

- **Automatic logout after a set period**  
  → Prevent session hijacking if admin forgets to log out.

---

## 5. Update plugin
