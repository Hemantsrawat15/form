## Step 1:- Delete the current database by using DROP DATABASE drdo_db;

## Step 2:- Create new database "drdo_db", and then in phpmyadmin sql panel write and execute this

```
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2025 at 10:59 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `drdo_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `forms`
--

CREATE TABLE `forms` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `form_name` varchar(255) NOT NULL,
  `form_template` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`form_template`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `form_submissions`
--

CREATE TABLE `form_submissions` (
  `id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `submission_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`submission_data`)),
  `status` enum('submitted','in_review','approved','rejected') NOT NULL DEFAULT 'submitted',
  `generated_pdf_path` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `forms`
--
ALTER TABLE `forms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_forms_group` (`group_id`);

--
-- Indexes for table `form_submissions`
--
ALTER TABLE `form_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_submissions_form` (`form_id`),
  ADD KEY `fk_submissions_user` (`user_id`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_name` (`group_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `forms`
--
ALTER TABLE `forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `form_submissions`
--
ALTER TABLE `form_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `forms`
--
ALTER TABLE `forms`
  ADD CONSTRAINT `fk_forms_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `form_submissions`
--
ALTER TABLE `form_submissions`
  ADD CONSTRAINT `fk_submissions_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_submissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
```


## Step 3:- Git clone this repo, do all the composer thing and all.

## Step 4:- Open Postman and new POST request http://localhost/drdo/backend/api/auth/signup.php
payload:-
```
{
    "email": "testuser15@example.com",
    "password": "password123"
}
```
and send this creates a new user

## Step 5:- Open Postman and new POST request http://localhost/drdo/backend/api/auth/login.php
payload:-
```
{
    "email": "testuser15@example.com",
    "password": "password123"
}
```
This will generate this when send
```
{
    "success": true,
    "message": "Login successful.",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0IiwiYXVkIjoiaHR0cDovL2xvY2FsaG9zdCIsImlhdCI6MTc2MTc3Mzk3NSwiZXhwIjoxNzYxNzc3NTc1LCJkYXRhIjp7InVzZXJfaWQiOjEwfX0.QOqEEnJrZvnpAxftgdNleEv95qoXdcFCFHO2FwJ9bAk",
    "user": {
        "id": 10,
        "email": "testuser15@example.com"
    }
}
```
Copy the token from here

## Step 6:- Open frontend at localhost/drdo/frontend
Login->Create Group-> MMG

## Step 7:- New POST request http://localhost/drdo/backend/api/forms/add.php

Go to authorization ( In the same line where body is present )
1. Auth type-> Bearer Token ( Paste the token )
2. body ->
```
[
  {
    "group_id": 2,
    "form_name": "Indent Check List Complete Form",
    "form_template": {
      "html": "<style>body {font-family: Arial, sans-serif; font-size: 13px; margin: 0; color: #171717;} .page-break {page-break-after: always;} .doc-header {text-align: center; font-size: 17px; font-weight: bold; margin-bottom: 10px; text-decoration: underline;} table.checklist {width: 100%; border-collapse: collapse; margin-bottom: 25px;} table.checklist th, table.checklist td {border: 1px solid #222; font-size: 13px; padding: 6px 8px; vertical-align: top; word-break: break-word;} table.checklist th {background: #f7f7f7; text-align: center; font-weight: bold;} .checkbox-col {text-align: center; width: 32px; font-size: 15px;} .page-col {text-align: center; width: 80px;} .doc-col {font-size: 13px;} .sub-list {padding-left: 12px; font-size: 13px;} .double-top {border-top: 2.5px double #222 !important;} </style><!-- Page 1 --><div class='doc-header'>Indent Check List – User (for Estimated Cost > Rs. 25,000/-)</div><table class='checklist'><tr class='double-top'><th style='width:34px;'>S No.</th><th class='doc-col'>Document/Point</th><th class='page-col'>Page No.</th><th class='checkbox-col'>Yes</th><th class='checkbox-col'>No</th></tr><tr><td>1</td><td>Statement of Case (SoC)/ Justification is enclosed with demand form with following<div class='sub-list'>a) Justification for the requirement and quantity</div><div class='sub-list'>b) Justification for the proposed mode of bidding (if not an open bidding)</div><div class='sub-list'>c) Existing Holder, if any</div><div class='sub-list'>d) Cost of the item</div><div class='sub-list'>e) Budget Head</div><div class='sub-list'>f) Self Certificate (mentioning that the specifications are generic except PAC or ST)</div></td><td></td><td></td><td></td></tr><tr><td>2</td><td>Specifications</td><td></td><td></td><td></td></tr><tr><td>3</td><td>Delivery Period, Warranty, AMC, CAMC (if required)</td><td></td><td></td><td></td></tr><tr><td>4</td><td>Delivery Location</td><td></td><td></td><td></td></tr><tr><td>5</td><td>List of Deliverables</td><td></td><td></td><td></td></tr><tr><td>6</td><td>Inspection/Acceptance Test Procedure</td><td></td><td></td><td></td></tr><tr><td>7</td><td>Vendor Qualification Criteria (VQC), if any</td><td></td><td></td><td></td></tr><tr><td>8</td><td>Scope and period of work (in case of procurement of services)</td><td></td><td></td><td></td></tr><tr><td>9</td><td>Cost of the proposal/ Budgetary Estimate</td><td></td><td></td><td></td></tr><tr><td>10</td><td>Mode of Bidding/ Repeat Order: LT/OT/Single Tender/PBM/SWOD</td><td></td><td></td><td></td></tr><tr><td>11</td><td>Proprietary Article Certificate (PAC) as per format at DRDO.DM.02 for proprietary items</td><td></td><td></td><td></td></tr><tr><td>12</td><td>Justification for PAC</td><td></td><td></td><td></td></tr><tr><td>13</td><td>Justification for procurement on Single Bidding Mode (SBM) as per DRDO.DM.03</td><td></td><td></td><td></td></tr><tr><td>14</td><td>Single Source Certificate DM.03</td><td></td><td></td><td></td></tr><tr><td>15</td><td>Requirement of insurance cover</td><td></td><td></td><td></td></tr><tr><td>16</td><td>Reference of projected demand in the Forecast Budget Estimate (FBE) (Else reasons for non-reflection in FBE justification)</td><td></td><td></td><td></td></tr><tr><td>17</td><td>Free Issue Material, if any</td><td></td><td></td><td></td></tr><tr><td>18</td><td>GeM Availability Certificate - User</td><td></td><td></td><td></td></tr><tr><td>19</td><td>All Documents to be Signed &amp; Countersigned by User &amp; AD</td><td></td><td></td><td></td></tr></table><div class='page-break'></div><!-- Page 2 --><div class='doc-header'>Indent Check List – GH/AD (for Estimated Cost > Rs. 25,000/-)</div><table class='checklist'><tr class='double-top'><th style='width:34px;'>S No.</th><th class='doc-col'>Document/Point</th><th class='page-col'>Page No.<br>(if applicable)</th><th class='checkbox-col'>Yes</th><th class='checkbox-col'>No</th></tr><tr><td>1</td><td>Existing holding of indented stores vis-à-vis consumption pattern or proposed utilization.</td><td></td><td></td><td></td></tr><tr><td>2</td><td>Confirm that the necessity is absolute and there is no duplication.</td><td></td><td></td><td></td></tr><tr><td>3</td><td>Check against splitting of demand to avoid approval of higher CFAs.</td><td></td><td></td><td></td></tr><tr><td>4</td><td>Confirmation that the specifications mentioned are generic and do not contain any brand name/part/model number except by way of indication of comparable quality.</td><td></td><td></td><td></td></tr><tr><td>5</td><td>Recommend mode of bidding with justification/comment on justification given by indentor for recommending Single/Limited/PAC mode of bidding or Repeat Order or RC or SWOD, as applicable. Also comment on justification for choosing un-registered vendor, if any, and need of pre-bid conference, if required.</td><td></td><td></td><td></td></tr><tr><td>6</td><td>Confirm that proposed procurement is part of an approved annual build/up project procurement plan with reference to the relevant entry, else record reasons for its non-reflection.</td><td></td><td></td><td></td></tr><tr><td>7</td><td>Ascertain whether proposed procurement requires any other complementary/supplementary expenditure such as on hardware, software, Civil works. If so, provide details thereof.</td><td></td><td></td><td></td></tr><tr><td>8</td><td>Specify a realistic time for MMG to process the approved demand till the supply order which normally should not exceed one year.</td><td></td><td></td><td></td></tr><tr><td>9</td><td>Comment on justifications given for dispensation from e-publishing and other waivers, if requested.</td><td></td><td></td><td></td></tr><tr><td>10</td><td>Comment on eligibility criteria for bidders as per Public Procurement (Preference to Make in India), Order-2017 as amended.</td><td></td><td></td><td></td></tr><tr><td>11</td><td>Comment on proposed VQC and special terms and conditions.</td><td></td><td></td><td></td></tr><tr><td>12</td><td>Comment on applicability of “Growth of Work” (if any)</td><td></td><td></td><td></td></tr><tr><td>13</td><td>Comment on the requirement of Expenditure Sanction along with demand approval.</td><td></td><td></td><td></td></tr><tr><td>14</td><td>Confirm GeM Availability Report</td><td></td><td></td><td></td></tr><tr><td>15</td><td>Confirm User &amp; AD Signature on all documents as per “Indent Checklist” - User</td><td></td><td></td><td></td></tr></table><div class='page-break'></div><!-- Page 3 --><div class='doc-header'>Role and Responsibilities of Lab</div><table class='checklist'><tr class='double-top'><th style='width:34px;'>S No.</th><th class='doc-col'>Document/Point</th><th class='page-col'>Page No.</th><th class='checkbox-col'>Yes</th><th class='checkbox-col'>No</th></tr><tr><td>1</td><td>Non-availability endorsement will be made for centrally stocked items. This endorsement is not required in case of service/maintenance contracts.</td><td></td><td></td><td></td></tr><tr><td>2</td><td>Ascertain whether the indented stores are covered under the purchase/ price preference and product reservation policy issued by Govt. of India and DRDO HQrs as applicable and recommend suitable action.</td><td></td><td></td><td></td></tr><tr><td>3</td><td>That demand has not been split to avoid sanction of higher CFA.</td><td></td><td></td><td></td></tr><tr><td>4</td><td>Scrutinize the special terms and conditions in RFP.</td><td></td><td></td><td></td></tr><tr><td>5</td><td>The eligibility criteria for bidders as per Public Procurement (Preference to Make in India), Order-2017 as amended has been followed.</td><td></td><td></td><td></td></tr><tr><td>6</td><td>Explore the possibility of bulk purchase of common use items, PCs, spares for other standard equipment/machinery to derive quantity discount.</td><td></td><td></td><td></td></tr><tr><td>7</td><td>Endorse details of previous procurements, if any, in last three years including quantity and prices.</td><td></td><td></td><td></td></tr><tr><td>8</td><td>Specify the registration status of proposed vendors in case of Single/Limited/PAC mode of bidding.</td><td></td><td></td><td></td></tr><tr><td>9</td><td>Check the applicability of issue of GST Exemption and/or Custom Duty Exemption for the proposed procurement. Further, if CDEC is proposed to be issued, applicable para and/or sub para number of the relevant notification would be indicated. In case of PAC mode of bidding, concurrence of finance on PAC certificate would be taken for cases where financial concurrence is otherwise not required for demand approval.</td><td></td><td></td><td></td></tr><tr><td>10</td><td>Record consolidated values of expenditure booked, commitments entered and cases in the pipeline for procurement in sanctioned project.</td><td></td><td></td><td></td></tr><tr><td>11</td><td>Ensure availability of funds in relevant budget head at the time of expected cash outgo.</td><td></td><td></td><td></td></tr><tr><td>12</td><td>Fix an amount for EMD between 2% to 5% of the estimated cost.</td><td></td><td></td><td></td></tr><tr><td>13</td><td>Fix a percentage for Performance Security Bond as per para 6.43.2(a), which would be taken from the successful bidder.</td><td></td><td></td><td></td></tr><tr><td>14</td><td>Scrutinize the estimated cost of the proposal.</td><td></td><td></td><td></td></tr><tr><td>15</td><td>Scrutinize the requirement of ,“Growth of Work” if proposed.</td><td></td><td></td><td></td></tr><tr><td>16</td><td>Recommend the requirement of Expenditure Sanction on cost not exceeding basis, subject to compliance of terms and conditions of the RFP, along with demand approval.</td><td></td><td></td><td></td></tr><tr><td>17</td><td>Check whether CNC is required to be convened per para 8.5.1 for COTS items/certain services etc. If CNC is not envisaged, same should be explicitly brought to the notice of CFA at the time of demand approval.</td><td></td><td></td><td></td></tr></table><div class='page-break'></div><!-- Page 4 --><div class='doc-header'>Indent Check List – GH/AD</div><table class='checklist'><tr class='double-top'><th style='width:34px;'>S No.</th><th class='doc-col'>Document/Point</th><th class='page-col'>Page No.</th><th class='checkbox-col'>Yes</th><th class='checkbox-col'>No</th></tr><tr><td>1</td><td>Copy of demand as per format DRDO.DM.01 with SoC.</td><td></td><td></td><td></td></tr><tr><td>2</td><td>Check-list signed/countersigned by the Director/Program Director as per Part II of DRDO.DM.01.</td><td></td><td></td><td></td></tr><tr><td>3</td><td>Copy of Draft RFP or all relevant details as per DRDO.BM.02.</td><td></td><td></td><td></td></tr><tr><td>4</td><td>Copy of PAC as per format DRDO.DM.02, if applicable.</td><td></td><td></td><td></td></tr><tr><td>5</td><td>Copy of detailed justification for procurement through single bidding mode as per format DRDO.DM.03, if applicable.</td><td></td><td></td><td></td></tr><tr><td>6</td><td>Duly filled-in questionnaire for acceptance of necessity in case of Capital procurement as per format DRDO.DM.04, where applicable.</td><td></td><td></td><td></td></tr><tr><td>7</td><td>List of vendors with vendor registration/enlistment No. and basis of selection of vendors (for Limited Bidding Mode (LBM)/Single Bidding Mode (SBM) only).</td><td></td><td></td><td></td></tr><tr><td>8</td><td>EOI/RFI report, if applicable.</td><td></td><td></td><td></td></tr><tr><td>9</td><td>Scope of Free Issue Material (FIM).</td><td></td><td></td><td></td></tr><tr><td>10</td><td>Justification for waiver of e-publishing, e-procurement and any other terms and conditions, if required.</td><td></td><td></td><td></td></tr></table>"
    }
  }
]
```

## Step 8: - Access and try it from the frontend

