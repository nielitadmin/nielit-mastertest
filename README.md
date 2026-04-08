# 🎓 NIELIT Computer Based Test (CBT) & Exam Logistics Portal

A highly secure, multi-role web application designed for the National Institute of Electronics & Information Technology (NIELIT). This platform handles the end-to-end lifecycle of computer-based testing, from Training Partner bulk bookings and Candidate practice payments to secure, locked-down exam environments and financial verifications.

## ✨ Key Features

### 👨‍🎓 Candidate Portal
* **Secure Exam Environment:** Enforces a locked full-screen mode to prevent cheating and tab-switching.
* **Smart Exam Timer:** Real-time countdowns that differentiate between absolute wall-clock times (formal exams) and relative durations (practice modes).
* **Real-Time Auto-Save:** Synchronizes answers to the database instantly via AJAX/Fetch API.
* **Google OAuth Integration:** Seamless single sign-on (SSO) for candidates.
* **Practice Mode:** Unlockable practice exams via a ₹50 verification fee.

### 🏢 Training Partner (TP) Portal
* **Bulk Booking:** Allows institutes to book exam slots for multiple candidates.
* **Payment Uploads:** Secure portal to upload UTR transaction receipts and screenshots for administrative approval.

### 💰 Finance Department Portal
* **Verification Dashboard:** Dedicated UI to approve/reject Training Partner bulk payments and Candidate practice fees.
* **Live Statistics:** Real-time revenue tracking and pending action counters.
* **Anti-BFCache Security:** Strict header and JavaScript cache-busting to prevent back-button data leaks after logout.

### 👨‍💼 Coordinator Portal
* **Logistics Management:** Dashboard for regional exam coordinators.
* **Secure Recovery:** 6-digit OTP-based password recovery via email integration.

## 🛠️ Technology Stack

* **Backend:** Core PHP (Session Management, PDO, RESTful APIs)
* **Database:** PostgreSQL (`nielit_cbt_mock`)
* **Frontend:** HTML5, CSS3, Vanilla JavaScript, FontAwesome
* **Typography:** Plus Jakarta Sans & Open Sans
* **Third-Party Integrations:** * Brevo (Sendinblue) API - For secure OTP and transactional emails.
  * Google OAuth 2.0 API - For Candidate authentication.

## 🔒 Security Implementations
* **Database Integrity:** Parameterized PDO queries to prevent SQL Injection.
* **Authentication:** `password_hash()` and `password_verify()` for all user credentials.
* **Session Security:** Strict session destruction, cookie clearing, and BFCache (Back-Forward Cache) prevention on logout.
* **Environment Protection:** Sensitive configuration files (`mailer.php`, `database.php`, `candidate-login.php`) are completely `.gitignore`d to prevent API key leaks.

## 🚀 Local Installation & Setup

### 1. Prerequisites
* PHP 8.0 or higher
* PostgreSQL
* Local server environment (XAMPP, WAMP, or similar)

### 2. Clone the Repository
```bash
git clone [https://github.com/RitwikSonam/cbt_portal.git](https://github.com/RitwikSonam/cbt_portal.git)
cd cbt_portal