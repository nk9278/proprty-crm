ALTER TABLE leads ADD COLUMN pipeline_stage_id INT NULL;
ALTER TABLE leads ADD FOREIGN KEY (pipeline_stage_id) REFERENCES pipeline_stages(id) ON DELETE SET NULL;
