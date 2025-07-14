# UpLearnly PDF Course Store

This project is a single-page PHP/MySQL web application that sells PDF courses under the brand **UpLearnly**. It offers a modern, responsive, and interactive interface for users to browse courses, register/login, manage a shopping cart, and simulate payments. Admins can view and confirm transactions.

---

## Features

- **Course Listings**: Display 8 distinct courses with images, descriptions, prices, and "Add to Cart" buttons.
- **User Management**: Register and login with role-based access (`user` and `admin`).
- **Shopping Cart**: Session-based cart managed via AJAX for seamless updates; view, remove items, and see real-time cart count.
- **Payment Simulation**: Enter transaction details after scanning a QR code; purchase gets logged with status 'Pending'.
- **Admin Panel**: View all transactions, confirm pending orders, and see detailed info.
- **Responsive & Modern UI**: Clean, professional design inspired by "Claude Sonnet 3.7" style, with animated fade-ins, hero slider, and pill-shaped buttons.
- **Secure Password Handling**: Passwords hashed with PHP's `password_hash()`.
- **Session Management & AJAX**: For cart updates and interactivity without full page reloads.

---

## Setup Instructions

### 1. Prepare the Database

- Import `database.sql` into your MySQL server:

```bash
mysql -u your_username -p < database.sql