# Internship Tracking System — Data Modeling Wireframes

Low-fidelity (gray-box) wireframes that show the layout and the **data
fields** of each screen.

- **Rendered image:** [`wireframes.png`](wireframes.png)
- **Viewable / printable page:** [`wireframes.html`](wireframes.html)

---

## Screens

| # | Screen | Role | Main data shown |
|---|---|---|---|
| 1 | **Login** | All | `users`: email/username, password |
| 2 | **Student Dashboard** | Student | placement, progress %, hours, requirements, personal QR |
| 3 | **Attendance & Hours** | Student | `attendance`: time_in, time_out, rendered_hours |
| 4 | **Requirements** | Student | `requirement_submissions`: status, remarks |
| 5 | **Dashboard** | Coordinator | counts, review queue |
| 6 | **Students** | Coordinator | `students`: student_number, full_name, course_year_section |
| 7 | **Scan Attendance** | Supervisor | `attendance`: qr_scan_reference, time_in/out |
| 8 | **Reports** | Coordinator | date filters, report type, export CSV / print |

---

## Conventions

- Gray boxes = interface elements / data placeholders.
- **Orange** = primary action (button).
- Dashed outlines = panels / cards.
- Tables show the main entity columns.

These wireframes map 1:1 to the implemented pages in `student/`,
`coordinator/`, and `supervisor/`, and to the entities in
[`ERD.md`](ERD.md) / [`EERD.md`](EERD.md).
