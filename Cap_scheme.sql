DROP DATABASE IF EXISTS capacity_estimator;
CREATE DATABASE capacity_estimator;
USE capacity_estimator;

CREATE TABLE arts (
    artId   INT AUTO_INCREMENT PRIMARY KEY,
    artName VARCHAR(100) NOT NULL
);

CREATE TABLE teams (
    teamId   INT AUTO_INCREMENT PRIMARY KEY,
    teamName VARCHAR(100) NOT NULL,
    artId    INT NOT NULL,
    FOREIGN KEY (artId) REFERENCES arts(artId)
);

CREATE TABLE persons (
    personId INT AUTO_INCREMENT PRIMARY KEY,
    name     VARCHAR(100) NOT NULL,
    email    VARCHAR(100) UNIQUE NOT NULL
);

CREATE TABLE team_members (
    teamMemberId  INT AUTO_INCREMENT PRIMARY KEY,
    teamId        INT NOT NULL,
    personId      INT NOT NULL,
    role          VARCHAR(50) NOT NULL,
    allocationPct INT NOT NULL,
    FOREIGN KEY (teamId)   REFERENCES teams(teamId),
    FOREIGN KEY (personId) REFERENCES persons(personId)
);

CREATE TABLE program_increments (
    piId      INT AUTO_INCREMENT PRIMARY KEY,
    piName    VARCHAR(100) NOT NULL,
    artId     INT NOT NULL,
    startDate DATE,
    endDate   DATE,
    FOREIGN KEY (artId) REFERENCES arts(artId)
);

CREATE TABLE iterations (
    iterationId   INT AUTO_INCREMENT PRIMARY KEY,
    iterationName VARCHAR(100) NOT NULL,
    piId          INT NOT NULL,
    startDate     DATE NOT NULL,
    endDate       DATE NOT NULL,
    FOREIGN KEY (piId) REFERENCES program_increments(piId)
);

CREATE TABLE capacities (
    capacityId  INT AUTO_INCREMENT PRIMARY KEY,
    teamId      INT NOT NULL,
    iterationId INT NOT NULL,
    storyPoints DECIMAL(10,2) NOT NULL,
    createdAt   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teamId)      REFERENCES teams(teamId),
    FOREIGN KEY (iterationId) REFERENCES iterations(iterationId)
);