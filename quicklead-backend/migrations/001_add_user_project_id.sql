-- Add project_id column to users
ALTER TABLE users ADD COLUMN project_id INT NULL;
-- Add foreign key constraint to projects
ALTER TABLE users ADD CONSTRAINT fk_users_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;
