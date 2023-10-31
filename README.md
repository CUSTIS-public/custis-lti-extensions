# modeus-sync-plugin

Плагин к Moodle, выполняющий синхронизацию данных LMS Moodle с Modeus.
Для взаимодействия с Modeus использует API [LMS Adapter](https://modeus-gitlab.custis.ru/Integration/backend-lmsadapter).

Реализован в виде 4 фоновых задач Moodle, запускающихся по настроенному расписанию.
Задачи (в предпочтительном порядке работы):

- pull_courses (создает курсы в Moodle по РМУПам)
- push_courses (отправляет в LMS Adapter существующие курсы Moodle)
- pull_members (добавляет в курсы Moodle студентов и преподавателей, согласно наполнению команд РМУПа)
- push_grades  (отправляет в LMS Adapter поставленные студентам оценки)