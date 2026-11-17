CREATE DATABASE IF NOT EXISTS rekruiting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rekruiting;

-- Таблица пользователей
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) DEFAULT '',
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Таблица вакансий
CREATE TABLE IF NOT EXISTS vacancies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    department VARCHAR(150) NOT NULL,
    security_level VARCHAR(50) NOT NULL,
    salary_from INT NOT NULL,
    experience_from INT NOT NULL,
    description TEXT NOT NULL
);

-- Таблица откликов
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vacancy_id INT NOT NULL,
    status VARCHAR(50) DEFAULT 'На рассмотрении',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vacancy_id) REFERENCES vacancies(id) ON DELETE CASCADE
);

-- Таблица резюме
CREATE TABLE IF NOT EXISTS resumes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    position VARCHAR(200) NOT NULL,
    experience TEXT,
    education TEXT,
    skills TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Заполнение таблицы вакансий тестовыми данными (чтобы сайт не был пустым)
INSERT INTO vacancies (title, department, security_level, salary_from, experience_from, description) VALUES
('Инженер-конструктор', 'Конструкторское бюро', '2-я форма', 70000, 3, 'Разработка конструкторской документации, участие в проектировании новых изделий, сопровождение производства.'),
('Технолог машиностроения', 'Технологический отдел', '1-я форма', 80000, 5, 'Разработка технологических процессов, оптимизация производства, внедрение новых технологий.'),
('Экономист-аналитик', 'Планово-экономический отдел', '3-я форма', 60000, 2, 'Анализ экономических показателей, подготовка отчетности, планирование бюджетов подразделений.');