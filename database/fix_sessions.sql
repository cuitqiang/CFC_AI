ALTER TABLE cuige_sessions 
    ADD COLUMN status VARCHAR(20) DEFAULT 'active',
    ADD COLUMN message_count INT DEFAULT 0;
