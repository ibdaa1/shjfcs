-- Create table for inspection attachments
-- This table stores additional PDF attachments for each inspection

CREATE TABLE IF NOT EXISTS `tbl_inspection_attachments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `inspection_id` INT(11) NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT(11) DEFAULT NULL,
  `uploaded_by_user_id` INT(11) DEFAULT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inspection_id` (`inspection_id`),
  KEY `idx_uploaded_by` (`uploaded_by_user_id`),
  CONSTRAINT `fk_attachment_inspection` FOREIGN KEY (`inspection_id`) 
    REFERENCES `tbl_inspections` (`inspection_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attachment_user` FOREIGN KEY (`uploaded_by_user_id`) 
    REFERENCES `Users` (`EmpID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for faster queries
CREATE INDEX idx_uploaded_at ON tbl_inspection_attachments(uploaded_at);
