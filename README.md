# MQAA ONLINE (AUDIT MQA)

## Description

This project digitizes Quality Management, Audit, and Assurance processes, aiming to cut down paper usage and time spent on score recaps. It enables practitioners to conduct electronic audits efficiently, streamlining planning, execution, and reporting. With detailed data recording, structured audit guidelines, and automated score summaries, it enhances efficiency and offers valuable insights for better decision-making in quality management.

⚠️ **Disclaimer**: This is not a complete project. Some code is not included and is intended solely for demonstration purposes as part of a portfolio. You can find the excluded files by checking the [.gitignore](.gitignore) file.

## Technologies Used

- **Backend**: Codeigniter 3 (RESTful), Node.js for background processes including receiving RabbitMQ messages, send notifications, and scheduling automatic audit completion
- **Frontend**: jQuery, Bootstrap
- **Database**: MySQL
- **Authentication**: JWT
- **Pagination**: SQL seek method for paginated tables
- **Event Scheduling**: RabbitMQ

## Features

- **Create Audit**: Allows the selection of an audit form, checklist completion, and the input of findings if present.
- **Get Notified**: Area leaders receive notifications indicating their area has been audited. They are required to review findings, input an action plan, and sign off.
- **Review Findings and Verification by Leader**: Enables leaders to review findings, input an action plan, and sign off.
- **Audit History and status**: Offers a comprehensive view of audit history and current status updates.
- **Follow-Up on Action Plan**: Conducts a follow-up the day after the audit to check the execution status of the action plan.
- **Set Up Area Leaders**: Configuration to designate responsible leaders for specific areas.
- **Display Summary and Scores**: Summarizes audit results, includes a database of audits, weekly scores by audit item, scores by area, weekly findings, and more.
- **Export**: Allows the export of audit summaries to Excel files.


## Screenshots

### 1. Create Audit
<p align="center">
  <img src="screenshots/mqa-create-audit.png" alt="Create audit" width="50%">
  <br>Image 1.1. - Create audit
</p>
<p align="center">
  <img src="screenshots/mqa-input-finding.png" alt="Input finding" width="100%">
  <br>Image 1.2. - Input finding
</p>

### 2. Get Notified
<p align="center">
  <img src="screenshots/mqa-email-notification.png" alt="Email notification" width="50%">
  <br>Image 2.1. - Email notification
</p>
<p align="center">
  <img src="screenshots/mqa-notifications.png" alt="Notifications page" width="50%">
  <br>Image 2.2. - Notifications page
</p>

### 3. Review Findings and Verification by Leader
<p align="center">
  <img src="screenshots/mqa-input-action-plan.png" alt="Input action plan" width="100%">
  <br>Image 3.1. - Input action plan
</p>
<p align="center">
  <img src="screenshots/mqa-verification-by-leader.png" alt="Leader verification" width="50%">
  <br>Image 3.2. - Leader verification
</p>

### 4. Audit History and status
<p align="center">
  <img src="screenshots/mqa-audit-history.png" alt="Audit history" width="50%">
  <br>Image 4. - Audit history
</p>

### 5. Follow-Up on Action Plan
<p align="center">
  <img src="screenshots/mqa-status-follow-up.png" alt="Audit to follow up" width="50%">
  <br>Image 5.1. - Audit to follow up
</p>
<p align="center">
  <img src="screenshots/mqa-follow-up-action-plan.png" alt="Follow up audit" width="50%">
  <br>Image 5.2. - Follow up audit
</p>

### 6. Set Up Area Leaders
<p align="center">
  <img src="screenshots/mqa-setup-leader-for-area.png" alt="Set up area leaders" width="100%">
  <br>Image 6. - Set up area leaders
</p>

### 7. Display Summary and Scores
<p align="center">
  <img src="screenshots/mqa-score-summary.png" alt="MQA score summary" width="50%">
  <br>Image 7.1. - MQA score summary
</p>
<p align="center">
  <img src="screenshots/mqa-audit-database.png" alt="MQA audit database" width="100%">
  <br>Image 7.2. - MQA audit database
</p>
<p align="center">
  <img src="screenshots/mqa-score-by-item.png" alt="MQA item score" width="50%">
  <br>Image 7.3. - MQA item score
</p>
<p align="center">
  <img src="screenshots/mqa-findings.png" alt="MQA Findings" width="50%">
  <br>Image 7.4. - MQA Findings
</p>
<p align="center">
  <img src="screenshots/mqa-bsc-score.png" alt="MQA BSC Score" width="100%">
  <br>Image 7.5. - MQA BSC Score
</p>

### 8. Export
<p align="center">
  <img src="screenshots/mqa-export.png" alt="Export as excel" width="50%">
  <br>Image 8. - Export as excel
</p>

## Support and Contact
For any support or feedback, please contact us at mfahmiirfan@gmail.com.
