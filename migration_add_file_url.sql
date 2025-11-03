-- Migration: Add file_url column to messages table
-- Run this if you already have an existing database

USE newsletter_wa;

-- Check if column doesn't exist before adding
ALTER TABLE messages 
ADD COLUMN IF NOT EXISTS file_url VARCHAR(500) NULL AFTER delay_days;
