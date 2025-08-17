Here's a **concise Product Requirements Document (PRD)** for your **demo application** of the **Bayside Surgical Centre Clinic Management System**. It includes **only the core functionalities** and **essential cybersecurity features** suitable for a minimal yet functional demo.

---

# **Product Requirements Document (PRD) – Bayside Surgical Centre Clinic Management System (Demo)**

## ✅ **Project Overview**

A lightweight web-based demo app to simulate core clinic operations of Bayside Surgical Centre. The system must manage outpatient services, patient registration, scheduling, billing, attendance tracking, and basic cybersecurity protocols.

---

## 🧩 **Core Features (MVP Modules)**

### 1. **Patient Registration**

* Assign **unique Patient ID**
* Store **basic demographics** (Name, DOB, Contact, Insurance Info)
* Email/SMS confirmation on successful registration
* Must validate inputs for security (no special characters, valid formats)

### 2. **Appointment & Scheduling**

* Doctor calendar view (color-coded)
* **Book, edit, cancel appointments**
* SMS/Email reminders (via dummy API or alert message simulation)
* Prevent double-booking

### 3. **Outpatient Management**

* Create **patient visit record** (diagnosis, lab requests, prescription summary)
* Add charges for services: consultation, lab, X-ray
* Generate **bill/invoice** (simple summary with total cost)
* Option to mark as paid/unpaid

### 4. **Claims & Statements**

* Manual **insurance claim submission**
* Generate **simple statement** for non-insured patients
* Print/email simulation option

### 5. **Reports**

* Predefined reports:

  * Appointments by day/week
  * Revenue by month
  * Outpatient count
* Optional: Export report (CSV or PDF)

### 6. **Attendance Management (Admin Only)**

* Simple attendance tracker for staff
* Manual check-in/out OR dummy biometric simulation
* Display list of late/missing entries

---

## 🔐 **Cybersecurity Layer (Demo-Level)**

### A. **Cybersecurity Policy Integration (Minimal Set)**

* **Access Control**: Basic login system (role-based access – Admin, Staff)
* **Input Validation**: All forms must have:

  * Sanitization (remove/escape special chars)
  * Server-side validation
* **Audit Logging**:

  * Log login attempts
  * Track who accessed or modified patient data
* **Reverse Engineering Protection** (Mention only):

  * Source obfuscation (mention as future scope)

### B. **HIPAA Compliance Indicators (Simulated)**

| Requirement                      | Implementation             |
| -------------------------------- | -------------------------- |
| Authorization                    | Login & role-based access  |
| Data validation and sanitization | Input checks in all forms  |
| Application reverse engineering  | Marked as a future plan    |
| Audit Logging                    | Store access & change logs |

---

## 🧪 **Vulnerability Analysis (Demo Scope)**

### Target: SQL Injection Simulation

* **Injection point**: Login form (demonstrate safe vs unsafe query)
* Tools: **SQLMap**, **DVWA**, or **manual Python script**
* Screenshots: of command used, injection success/failure

### Suggested Fix:

* Use **parameterized queries**
* Add input sanitization
* Limit failed login attempts

---

## 📊 **Non-Functional Requirements**

* Platform: Web-based (MERN/Node/Flask/etc.)
* Must run locally or on a test server
* Simple UI (no complex animations)
* Clean form layouts and basic dashboards
* Basic mobile responsiveness (optional)

---

## 🔖 **Out of Scope for Demo**

* eClaims with real-time APIs
* PACS, HL7 Integration
* Mobile App
* Biometric Hardware Integration
* Real-time SMS gateways

---

## 📄 **Deliverables**

1. Functional web app (core modules only)
2. Cybersecurity policy document (1-2 pages)
3. Vulnerability analysis report (1-2 pages + screenshots)
4. Demo credentials (admin/staff/patient)

---

Let me know if you'd like me to generate:

* A UI mockup or wireframe
* Sample DB schema
* A sample Cybersecurity Policy doc
* The vulnerability test report template

I can also break this into **tasks for each team member** if needed.
