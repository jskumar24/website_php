-- Create the database

CREATE DATABASE IF NOT EXISTS plans_db;

-- Use the newly created database
USE plans_db;

-- Create the tbl_plans table

CREATE TABLE tbl_plans (id INT AUTO_INCREMENT PRIMARY KEY, -- Unique identifier for each plan
 plan_name VARCHAR(255) NOT NULL, -- Name of the plan
 plan_price DECIMAL(10, 2) NOT NULL, -- Price of the plan
 website_count INT NOT NULL, -- Number of websites allowed
 disk_space VARCHAR(50) NOT NULL, -- Disk space allowed (e.g., '10GB', 'Unlimited')
 bandwidth VARCHAR(50) NOT NULL, -- Bandwidth allowed (e.g., '100GB', 'Unlimited')
 databases_count INT NOT NULL, -- Number of databases allowed
 users_count INT NOT NULL, -- Number of users allowed
 email_accounts_count INT NOT NULL -- Number of email accounts allowed
);

-- Insert sample data into the tbl_plans table

INSERT INTO tbl_plans (plan_name, plan_price, website_count, disk_space, bandwidth, databases_count, users_count, email_accounts_count)
VALUES ('Basic Plan',
        5.99,
        1,
        '10GB',
        '100GB',
        2,
        1,
        5), ('Standard Plan',
             9.99,
             5,
             '50GB',
             '500GB',
             5,
             5,
             20), ('Premium Plan',
                   19.99,
                   10,
                   '100GB',
                   'Unlimited',
                   10,
                   10,
                   50), ('Unlimited Plan',
                         29.99,
                         -1,
                         'Unlimited',
                         'Unlimited',
                         -1,
                         -1,
                         -1);

-- Verify the inserted data

SELECT *
FROM tbl_plans;

