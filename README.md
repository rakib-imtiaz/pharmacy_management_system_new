# Bayside Surgical Centre - Clinic Management System

A modern, professional clinic management system built with PHP, MySQL, and Tailwind CSS. This system provides comprehensive management of patient records, appointments, outpatient visits, and billing.

## 🏥 Features

### Core Modules

- **Patient Management**: Register and manage patient records with unique IDs
- **Appointment Scheduling**: Book, edit, and cancel appointments with double-booking prevention
- **Outpatient Management**: Record patient visits with diagnosis, lab requests, and prescriptions
- **Billing System**: Generate invoices and track payments
- **User Management**: Role-based access control (Admin, Doctor, Staff)

### Security Features

- Input validation and sanitization
- SQL injection prevention with prepared statements
- Audit logging for all user actions
- Session-based authentication
- Role-based access control

### UI/UX Features

- Modern, responsive design with Tailwind CSS
- Mobile-friendly interface
- Smooth animations and transitions
- Professional medical color scheme
- Interactive dashboards with real-time statistics

## 🚀 Quick Start

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)

### Installation

1. **Clone or download the project**

   ```bash
   git clone <repository-url>
   cd pharmacy_management_system
   ```

2. **Set up the database**

   - Create a MySQL database named `clinic_demo`
   - Import the `db.sql` file to create tables and sample data

3. **Configure database connection**

   - Edit `includes/db_connect.php`
   - Update database credentials if needed

4. **Set up web server**

   - Point your web server to the project directory
   - Ensure PHP has write permissions

5. **Access the system**
   - Navigate to `http://localhost/pharmacy_management_system`
   - Use demo credentials to log in

## 👥 Demo Credentials

| Role   | Username | Password  |
| ------ | -------- | --------- |
| Admin  | admin    | admin123  |
| Doctor | drjones  | doctor123 |
| Staff  | nurseamy | staff123  |

## 📊 Database Schema

The system uses the following main tables:

- `patients` - Patient records and demographics
- `appointments` - Appointment scheduling
- `outpatient_visits` - Patient visit records
- `invoices` - Billing and payment tracking
- `users` - System users with roles
- `audit_logs` - Security audit trail

## 🛠️ Technical Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **CSS Framework**: Tailwind CSS
- **Icons**: Font Awesome
- **Fonts**: Inter (Google Fonts)

## 📱 Mobile Responsiveness

The system is fully responsive and works seamlessly on:

- Desktop computers
- Tablets
- Mobile phones

## 🔒 Security Considerations

- All user inputs are validated and sanitized
- SQL queries use prepared statements
- Session management with proper security
- Audit logging for compliance
- Role-based access control

## 📈 Future Enhancements

- Email/SMS notifications
- Advanced reporting and analytics
- Integration with medical devices
- Mobile app development
- Electronic health records (EHR) integration

## 🤝 Contributing

This is a demo system for educational purposes. For production use, additional security measures and testing should be implemented.

## 📄 License

This project is for educational and demonstration purposes.

---

**Bayside Surgical Centre** - Professional Clinic Management Solution
