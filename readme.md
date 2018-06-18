#PHP examples

####Email.php
This is an abstract class that contains the logic of email sending. It gets email and variables for template, automatically searches the template based on child class name, automatically sets Mailgun client instance, automatically fills template.

####SendEmail.php
Previous class gives full control on email sending. Still, it is not always necessary to use all its functionality, so we wanted to make a helper that will send email in one line instead of three. This class helps to do it.
It dynamically forms a name of email class based on called method and sets task to Beanstalkd (a row of tasks to implement an asynchronicity).
A watcher will take a task from Beanstalkd and call “_process” method of this class to start a typical pattern of email-sending.

####TestingRepository.php
This class implements CRUD operations for Testing entity with status and replace other events on the same dates.