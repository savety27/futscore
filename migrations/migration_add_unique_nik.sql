-- Migration: Add UNIQUE constraint to NIK columns
-- Date: 2026-01-30

-- Enforce uniqueness for NIK in players table
ALTER TABLE players ADD UNIQUE (nik);

-- Enforce uniqueness for NIK in officials table
ALTER TABLE officials ADD UNIQUE (nik);

-- Enforce uniqueness for NIK in bpjs_registrations table
ALTER TABLE bpjs_registrations ADD UNIQUE (nik);
