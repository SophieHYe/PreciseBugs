/*
 *  File name:  setup.sql
 *  Function:   to create the initial database schema for the CMPUT 391 project,
 *              Winter Term, 2015
 *  Author:     Prof. Li-Yan Yuan
 */
DROP TABLE family_doctor;
DROP TABLE pacs_images;
DROP TABLE radiology_record;
DROP TABLE users;
DROP TABLE persons;
DROP SEQUENCE record_id_seq;
DROP SEQUENCE image_id_seq;
DROP SEQUENCE person_id_seq;

/*
 *  To store the personal information
 */
CREATE TABLE persons (
   person_id int,
   first_name varchar(24),
   last_name  varchar(24),
   address    varchar(128),
   email      varchar(128),
   phone      char(10),
   PRIMARY KEY(person_id),
   UNIQUE (email)
);

CREATE SEQUENCE person_id_seq;

/*
 *  To store the log-in information
 *  Note that a person may have been assigned different user_name(s), depending
 *  on his/her role in the log-in  
 */
CREATE TABLE users (
   user_name varchar(24),
   password  varchar(24),
   class     char(1),
   person_id int,
   date_registered date,
   CHECK (class in ('a','p','d','r')),
   PRIMARY KEY(user_name),
   FOREIGN KEY (person_id) REFERENCES persons
);

/*
 *  to indicate who is whose family doctor.
 */
CREATE TABLE family_doctor (
   doctor_id    int,
   patient_id   int,
   FOREIGN KEY(doctor_id) REFERENCES persons,
   FOREIGN KEY(patient_id) REFERENCES persons,
   PRIMARY KEY(doctor_id,patient_id)
);

/*
 *  to store the radiology records
 */
CREATE TABLE radiology_record (
   record_id   int NOT NULL, 
   patient_id  int,
   doctor_id   int,
   radiologist_id int,
   test_type   varchar(24),
   prescribing_date date,
   test_date    date,
   diagnosis    varchar(128),
   description   varchar(1024),
   PRIMARY KEY(record_id),
   FOREIGN KEY(patient_id) REFERENCES persons,
   FOREIGN KEY(doctor_id) REFERENCES  persons,
   FOREIGN KEY(radiologist_id) REFERENCES  persons
);

CREATE SEQUENCE record_id_seq;


/*
 *  to store the pacs images
 */
CREATE TABLE pacs_images (
   record_id   int,
   image_id    int NOT NULL,
   thumbnail   blob,
   regular_size blob,
   full_size    blob,
   PRIMARY KEY(record_id,image_id),
   FOREIGN KEY(record_id) REFERENCES radiology_record
);

CREATE SEQUENCE image_id_seq;

CREATE INDEX descriptionIndex ON radiology_record(description) INDEXTYPE IS CTXSYS.CONTEXT;

CREATE INDEX diagnosisIndex ON radiology_record(diagnosis) INDEXTYPE IS CTXSYS.CONTEXT;

CREATE INDEX testTypeIndex On radiology_record(test_type) INDEXTYPE IS CTXSYS.CONTEXT;

CREATE INDEX firstNameIndex ON persons(first_name) INDEXTYPE IS CTXSYS.CONTEXT;

CREATE INDEX lastNameIndex ON persons(last_name) INDEXTYPE IS CTXSYS.CONTEXT;

/*
 * Insert into the database an admin account to allow initial set up of acounts
 */
 INSERT INTO persons values (000000, 'admin', 'admin', 'admin', 'admin', 'admin');
 INSERT INTO users values ('admin', 'admin', 'a', '0', to_date('2015-04-02', 'YYYY-MM-DD')); 
