Job Scheduler Feature/Attribute/Behavior Standard (JSFABS)
==========================================================

The Job Scheduler Feature/Attribute/Behavior Standard (JSFABS) is a 52 question survey of features, attributes, and behaviors that defines the minimum that a job scheduler should and should not have/be/do to be considered JSFABS-compliant.

Job scheduling software is software that executes a program or other code on a schedule set by the user.  Job scheduling software is generally referred to as cron/cron job/Task Scheduler software and what it is called is mostly based on the name of the software product.

Many software products exist on the market today that do not do what users of job scheduling software, both individuals and businesses of all sizes, actually want from job scheduling software.  JSFABS was created to establish the Standard by which any job scheduling software product can be objectively measured and graded to see if it meets minimum expectations.

Products Selected
-----------------

A number of products were selected for analysis and comparison in order to develop this Standard and the comparison chart for the survey:

* [Vixie/POSIX cron](https://github.com/vixie/cron) - Selected since it or close variants are currently the most widely deployed cron, residing on about 99% of all UNIX-based systems on Earth.  UNIX-based servers power an estimated 80% of Internet-facing systems and hundreds of millions of internal systems globally, including Mac OSX desktops/laptops.  Note that newer Vixie/POSIX cron derivatives/alternatives like cronie and bcron may have a marginally higher score.
* The [Koenig/POSIX `at` daemon](https://en.wikipedia.org/wiki/At_(command)) or `atd` - Selected since it is the most widely deployed `at` daemon, residing on about 99.99% of all UNIX-based systems on Earth.  The unfortunate problem with atd is that almost no one uses it despite being able to solve a variety of job scheduling problems that Vixie/POSIX cron and other solutions can't handle.  In fact, atd is so underutilized and misunderstood that it is [occasionally declared to be some sort of security problem](https://www.stigviewer.com/stig/red_hat_enterprise_linux_6/2017-12-08/finding/V-38641) that should be removed from servers.  The numbers in parenthesis in the tallies/scores below show a combined score with Koenig/POSIX atd where applicable.
* [fcron](http://fcron.free.fr/) - Lacks popularity but it has an impressive feature set as a direct Vixie/POSIX cron alternative/replacement on UNIX-style systems with features that most other direct cron alternatives don't have.
* [Microsoft Windows Task Scheduler](https://en.wikipedia.org/wiki/Windows_Task_Scheduler) - Selected because it runs on Windows, is widely used on Windows Server editions, and also contains a wide array of useful features.  Windows Server editions power about 20% of Internet-facing systems and hundreds of millions of internal business server systems globally.
* [Nextdoor Scheduler](https://github.com/Nextdoor/ndscheduler), [Airbnb Chronos](https://mesos.github.io/chronos/), [Quora Job Scheduler](https://quoraengineering.quora.com/Quoras-Distributed-Cron-Architecture) - Nextdoor, Airbnb, and Quora solutions were selected and are grouped together for the reason that those three companies independently looked around at various cron solutions, found existing solutions insufficient for their respective real-world business needs, came up with their own solutions that have roughly similar distributed/clustered designs, and published reasonable articles about their respective solutions online to be able to score them as a group.  There might be some minor point differentials as a result in the final score.
* [CubicleSoft xcron](https://github.com/cubiclesoft/xcron) - The culmination of a decade of experience in the field of scheduling tasks via various means and coming up with a variety of critical innovations along the way.  CubicleSoft xcron is also the reference implemention of the Job Scheduler Feature/Attribute/Behavior Standard (JSFABS).

Software such as systemd timers, Jenkins CI, and others were reviewed but not selected for the comparison chart since, while they have a job scheduler built-in, the scheduler portion is part of a larger ecosystem, which violates the software design philosophy of "do one thing and do it well."  However, since systemd is the default init system for most major Linux distros, a calculated score and final grade for systemd timers is included at the very end of this document.

Methodology and Definitions
---------------------------

The survey and the Standard consists of features, attributes, and behaviors.  Each is formulated as a Yes/No question.  Does the job scheduling software have feature/attribute/behavior XYZ?  A point is given if even a partial "Yes" is the answer.  No point is given if "No" is the answer.  There are 52 possible points.

A feature is defined as a concrete, objective piece of functionality that someone scheduling a job to run could require in order to run the job.  An example of a feature might be:  "The ability for the job scheduling software to send an email when the job completes."

An attribute is defined as something that someone might desire that is tangentially related to the software.  An example of an attribute might be:  "The job scheduling software was written by a team or organization rather than a single individual."

A behavior is defined as something the software should never do OR should always do.  An example of a behavior might be:  "The job scheduling software should never automatically run a job BEFORE the scheduled time."

The survey questions consist mostly of features with the occasional attribute and behavior.

Q1 - Does NOT start another process for a job by default when the job is currently running?
-------------------------------------------------------------------------------------------

Type:  Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |1      |
|Nextdoor, Airbnb, Quora |Yes?  |1      |
|Windows Task Scheduler  |Yes   |1      |
|fcron                   |Yes   |1 (1)  |
|Vixie/POSIX cron        |No    |0 (1)  |
|Koenig/POSIX at (atd)   |Yes   |1      |

Job scheduling software that blindly starts processes when the scheduled time arrives can easily trigger a Denial of Service of the entire system and/or cause data corruption by running multiple overlapping copies of the same job at the same time.

Vixie/POSIX cron and most alternatives do not get a point here.

xcron, Windows Task Scheduler, and fcron all do the correct thing.  They start out with a point on the board.

Most of the various distributed solutions temporarily lock a task before beginning execution to avoid running overlapping tasks, so a point is given.

Rescheduling a job in Koenig/POSIX atd would generally be done at the end of a job and therefore `atd` receives a point.

Q2 - Control the number of simultaneous running instances per job but default to a limit of one?
------------------------------------------------------------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |2      |
|Nextdoor, Airbnb, Quora |No    |1      |
|Windows Task Scheduler  |No    |1      |
|fcron                   |Yes   |2 (2)  |
|Vixie/POSIX cron        |No    |0 (1)  |
|Koenig/POSIX at (atd)   |No    |1      |

xcron and fcron allow refined control over how many simultaneous instances of each job can run at one time per job, but also correctly default to one process at a time so they each get a point.

Q3 - Runs on Windows?
---------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |3      |
|Nextdoor, Airbnb, Quora |No    |1      |
|Windows Task Scheduler  |Yes   |2      |
|fcron                   |No    |2 (2)  |
|Vixie/POSIX cron        |No    |0 (1)  |
|Koenig/POSIX at (atd)   |No    |1      |

xcron and Windows Task Scheduler run on Windows.  Both get a point.

Side note:  Windows has a deprecated `at.exe` command included with the OS.  However, it's not Koenig/POSIX `atd` but apparently was inspired to some extent by it.

Q4 - Supports starting processes on Windows with multiple Integrity levels?
---------------------------------------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |4      |
|Nextdoor, Airbnb, Quora |No    |1      |
|Windows Task Scheduler  |No    |2      |
|fcron                   |No    |2 (2)  |
|Vixie/POSIX cron        |No    |0 (1)  |
|Koenig/POSIX at (atd)   |No    |1      |

xcron can start processes on Windows with an Elevated (High Integrity) or a non-Elevated (Medium Integrity) level per user.  xcron gets a point.

Windows Task Scheduler cannot.

Q5 - Supports starting processes on Windows without storing user credentials?
-----------------------------------------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |5      |
|Nextdoor, Airbnb, Quora |No    |1      |
|Windows Task Scheduler  |No    |2      |
|fcron                   |No    |2 (2)  |
|Vixie/POSIX cron        |No    |0 (1)  |
|Koenig/POSIX at (atd)   |No    |1      |

xcron starts processes on Windows without requiring the credentials of the user.  xcron gets a point.

Windows Task Scheduler uses stored user credentials to start processes.

Q6 - Performs well on Windows?
------------------------------

Type:  Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |No*   |5      |
|Nextdoor, Airbnb, Quora |No    |1      |
|Windows Task Scheduler  |Yes*  |3      |
|fcron                   |No    |2 (2)  |
|Vixie/POSIX cron        |No    |0 (1)  |
|Koenig/POSIX at (atd)   |No    |1      |

Windows Task Scheduler itself generally performs well on Windows and therefore gets a point.  However, accessing the history for a task is a painfully slow experience.

xcron requires a number of included helper applications to function on Windows.  A lot of extra work is needed to notably improve performance.  xcron may run but it does not run as well as it could on Windows and therefore does not get a point here.

Q7 - Runs on Mac OSX?
---------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |6      |
|Nextdoor, Airbnb, Quora |Yes?  |2      |
|Windows Task Scheduler  |No    |3      |
|fcron                   |Yes   |3 (3)  |
|Vixie/POSIX cron        |Yes   |1 (2)  |
|Koenig/POSIX at (atd)   |Yes   |2      |

xcron, fcron, and Vixie/POSIX cron all get a point here.

Nextdoor/Airbnb/Quora and other distributed systems probably run on Mac OSX but are not likely intended to run in that environment other than for testing purposes.  They get a point here though.

All of the products now officially have at least one point on the board.

Q7 - Runs on Linux?
-------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |7      |
|Nextdoor, Airbnb, Quora |Yes   |3      |
|Windows Task Scheduler  |No    |3      |
|fcron                   |Yes   |4 (4)  |
|Vixie/POSIX cron        |Yes   |2 (3)  |
|Koenig/POSIX at (atd)   |Yes   |3      |

Linux is the primary target OS for most cron systems.  A point for everyone except Microsoft Windows Task Scheduler for obvious reasons.

Q8 - Follows UNIX security model on *NIX?
-----------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |8      |
|Nextdoor, Airbnb, Quora |No    |3      |
|Windows Task Scheduler  |No    |3      |
|fcron                   |Yes   |5 (5)  |
|Vixie/POSIX cron        |Yes   |3 (4)  |
|Koenig/POSIX at (atd)   |Yes   |4      |

xcron, fcron, Vixie/POSIX cron, and Koenig/POSIX atd all get a point for following the UNIX security model on UNIX-like systems.

Microsoft Windows Task Scheduler isn't relevant for obvious reasons.

Nextdoor, Airbnb, and Quora don't get a point here since their respective solutions are database-driven and bypass the UNIX security model as a result.

Q9 - Follows Windows security model on Windows?
-----------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |9      |
|Nextdoor, Airbnb, Quora |No    |3      |
|Windows Task Scheduler  |Yes   |4      |
|fcron                   |No    |5 (5)  |
|Vixie/POSIX cron        |No    |3 (4)  |
|Koenig/POSIX at (atd)   |No    |4      |

Most task/job scheduling solutions generally don't run on Windows for a variety of reasons.

xcron and Windows Task Schduler follow the Windows security model on Windows and both get a point.

Q10 - Includes a GUI?
---------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |No    |9      |
|Nextdoor, Airbnb, Quora |Yes   |4      |
|Windows Task Scheduler  |Yes   |5      |
|fcron                   |No    |5 (5)  |
|Vixie/POSIX cron        |No    |3 (4)  |
|Koenig/POSIX at (atd)   |No    |4      |

Most solutions don't have a GUI that is included with the product itself.

Nextdoor, Airbnb, and Quora use a web interface to manage scheduled tasks and therefore get a point.

Windows Task Scheduler provides a native GUI interface and also gets a point.

Q11 - Job/schedule parser errors do NOT affect other job schedules?
-------------------------------------------------------------------

Type:  Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |10     |
|Nextdoor, Airbnb, Quora |Yes   |5      |
|Windows Task Scheduler  |Yes   |6      |
|fcron                   |No    |5 (6)  |
|Vixie/POSIX cron        |No    |3 (5)  |
|Koenig/POSIX at (atd)   |Yes   |5      |

Vixie/POSIX cron and derivatives are notorious for refusing to run any of the jobs in a crontab if even the slightest error exists, so no point is given here.  fcron also apparently exhibits the problem, so it also doesn't get a point.

All of the other solutions get a point since an error in a job is either impossible or errors in schedule parsing don't affect other jobs.

Notably, xcron identifies spelling mistakes of various job options during the reload/rebuild of a schedule.

Q12 - Can the user assign a name for each job/schedule?
-------------------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |11     |
|Nextdoor, Airbnb, Quora |No?   |5      |
|Windows Task Scheduler  |Yes   |7      |
|fcron                   |No    |5 (6)  |
|Vixie/POSIX cron        |No    |3 (5)  |
|Koenig/POSIX at (atd)   |No    |5      |

xcron and Windows Task Scheduler jobs are assigned a name by the user that can be used later on to readily identify the job by that name.  Both get a point.

The other systems don't appear to have name assignment capabilities.

Q13 - Originally designed and written within a team?
----------------------------------------------------

Type:  Attribute

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |No    |11     |
|Nextdoor, Airbnb, Quora |Yes   |6      |
|Windows Task Scheduler  |Yes   |8      |
|fcron                   |No    |5 (6)  |
|Vixie/POSIX cron        |No    |3 (5)  |
|Koenig/POSIX at (atd)   |No    |5      |

Nextdoor, Airbnb, Quora, and Microsoft Windows Task Scheduler get a point here for at least attempting to design by committee.

The others were primarily and originally designed and written by individuals.

Q14 - Run code in any scripting/programming language?
-----------------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |12     |
|Nextdoor, Airbnb, Quora |No    |6      |
|Windows Task Scheduler  |Yes   |9      |
|fcron                   |Yes   |6 (7)  |
|Vixie/POSIX cron        |Yes   |4 (6)  |
|Koenig/POSIX at (atd)   |Yes   |6      |

Custom distributed cron systems have the unfortunate tendency to require code to be written in just one scripting or programming language.

The rest of the job scheduling systems get a point for allowing the user to write code in the language of their choice.

Q15 - Can pick up/run missed jobs?
----------------------------------

Type:  Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |13     |
|Nextdoor, Airbnb, Quora |Yes   |7      |
|Windows Task Scheduler  |Yes   |10     |
|fcron                   |Yes*  |7 (8)  |
|Vixie/POSIX cron        |No    |4 (7)  |
|Koenig/POSIX at (atd)   |Yes   |7      |

If a system is turned off for an extended period of time, does the software have the ability to run the jobs it missed when the system is turned on again?

Vixie/POSIX cron does not run missed jobs.

The other solutions have good support for this feature and each get a point.

Note that while fcron gets a point since it _technically_ runs missed jobs, it only saves the relevant information to disk every 30 minutes, which could be a problem in certain scenarios.

Q16 - Auto-delays missed schedules near boot?
---------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |14     |
|Nextdoor, Airbnb, Quora |No    |7      |
|Windows Task Scheduler  |No    |10     |
|fcron                   |No*   |7 (8)  |
|Vixie/POSIX cron        |No    |4 (7)  |
|Koenig/POSIX at (atd)   |No*   |7      |

When a system is booting up, it is usually very busy loading drivers, services, and applications.

Running missed schedules right at boot could cause the job to fail.

fcron and Koenig/POSIX atd can both somewhat accomplish this by waiting for the system load average to drop below a set level before running jobs, but that's not a good enough solution for a point here.

xcron gets a point by automatically delaying missed schedules for up to 5 minutes after the boot time.

Q17 - Supports schedule reload at start?
----------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |15     |
|Nextdoor, Airbnb, Quora |No    |7      |
|Windows Task Scheduler  |No    |10     |
|fcron                   |No    |7 (8)  |
|Vixie/POSIX cron        |Yes   |5 (8)  |
|Koenig/POSIX at (atd)   |No    |7      |

Most of the software that supports picking up missed jobs does not reload the schedule at startup of the software because doing so would cause it to not run missed jobs.

xcron has an option to force a schedule reload every time xcron starts up and therefore it gets a point.

Since Vixie/POSIX cron doesn't support running missed jobs and therefore always reloads schedules at startup, it also gets a point.

Q18 - Supports running jobs at boot?
------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |16     |
|Nextdoor, Airbnb, Quora |No    |7      |
|Windows Task Scheduler  |Yes   |11     |
|fcron                   |Yes   |8 (9)  |
|Vixie/POSIX cron        |Yes*  |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

Many local cron systems support triggering jobs to run at system boot via various means.  They each get a point.

Note that not all Vixie/POSIX cron products support running jobs at boot but most modern ones do.

Running job schedules at boot is not particularly applicable to distributed cron job systems.

Q19 - Supports minimum system uptime before running a scheduled job?
--------------------------------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |17     |
|Nextdoor, Airbnb, Quora |No    |7      |
|Windows Task Scheduler  |Yes   |12     |
|fcron                   |No    |8 (9)  |
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

Of the systems that support triggering a schedule at reboot, only xcron and Windows Task Scheduler support a minimum system uptime conditional feature before starting the job.  They each get point.

Q20 - Supports random delay start per-job/schedule?
---------------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |18     |
|Nextdoor, Airbnb, Quora |No    |7      |
|Windows Task Scheduler  |Yes   |13     |
|fcron                   |Yes*  |9 (10) |
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

xcron and Windows Task Scheduler can delay the start of a job by a random amount of time within a set range and therefore they each get a point.

fcron can apply jitter to a schedule up to a 2 minute, 15 second delay.  Since there is some limited support in fcron, it also gets a point.

systemd timers would get a point here too if it were being included since this is a frequently touted feature of systemd timers.

Q21 - Supports per-job timezones?
---------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |19     |
|Nextdoor, Airbnb, Quora |No?   |7      |
|Windows Task Scheduler  |Yes   |14     |
|fcron                   |Yes   |10 (11)|
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

In other words, can the timezone be set per job?

xcron, Windows Task Scheduler, and fcron all get a point here.

The distributed cron job systems most likely do not support per-job timezones.

Q22 - Supports complex date shifting?
-------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |20     |
|Nextdoor, Airbnb, Quora |No    |7      |
|Windows Task Scheduler  |No    |14     |
|fcron                   |No    |10 (11)|
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

Here are a few examples of date shifting:

* Closest weekday (M-F) to December 25th
* 3 days before the end of the month
* Second to last week of every other month with Monday as the first day of each week

xcron handles those and several other forms of complex date shifting with ease and gets a point.

Q23 - Supports sub 1-minute time resolution?
--------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |21     |
|Nextdoor, Airbnb, Quora |Yes   |8      |
|Windows Task Scheduler  |Yes   |15     |
|fcron                   |No    |10 (11)|
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

xcron, Nextdoor/Airbnb/Quora, and Windows Task Scheduler are able to specify times with a better than 1-minute time resolution and therefore each get a point.

Q24 - Supports job schedule dependencies?
-----------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |22     |
|Nextdoor, Airbnb, Quora |Yes   |9      |
|Windows Task Scheduler  |No    |15     |
|fcron                   |No    |10 (11)|
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

In other words, can one job schedule depend on another job schedule having completed successfully?

xcron and a couple of the distributed cron systems support dependencies and each get a point.

Q25 - Stop running sequential commands when stderr is output?
-------------------------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |23     |
|Nextdoor, Airbnb, Quora |No    |9      |
|Windows Task Scheduler  |No    |15     |
|fcron                   |No    |10 (11)|
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

Sequential commands are commands that are executed one after the other for a single job.

Return values can be bogus while stderr offers more reliable error detection.

By default, xcron will stop running sequential commands for a job after encountering output on stderr and therefore gets a point.

xcron also offers an option to treat stderr output as a warning rather than an error.

Q26 - Last line JSON result handling?
-------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |24     |
|Nextdoor, Airbnb, Quora |No    |9      |
|Windows Task Scheduler  |No    |15     |
|fcron                   |No    |10 (11)|
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

When a script/program returns a line of JSON as its last line of non-empty output on stdout, xcron can use that information to determine success/failure and store error, error code, and additional information returned.

This mechanism can also be used to pass information to the next run of the same job, rewrite the internal schedule, return custom statistics per job, and much, much more.

This innovative feature is a massive game changer.  xcron gets a point.

Q27 - Retry support for failed jobs?
------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |25     |
|Nextdoor, Airbnb, Quora |Yes   |10     |
|Windows Task Scheduler  |Yes   |16     |
|fcron                   |No    |10 (11)|
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

When a job fails, xcron, the distributed cron systems, and Windows Task Scheduler have options for automatically retrying the failed job and therefore each get a point.

Q28 - Customize each retry frequency per retry attempt per job schedule?
------------------------------------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |26     |
|Nextdoor, Airbnb, Quora |No    |10     |
|Windows Task Scheduler  |No    |16     |
|fcron                   |No    |10 (11)|
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

xcron supports complex, fully configurable, automatic retry frequencies per job and therefore gets a point.

Q29 - Can alert after a long run time?
--------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |27     |
|Nextdoor, Airbnb, Quora |Yes?  |11     |
|Windows Task Scheduler  |No    |16     |
|fcron                   |No    |10 (11)|
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

xcron and possibly the distributed systems can have each job be configured to send an alert after running for a certain amount of time and therefore each get a point.

Q30 - Can terminate a job after it runs for too long?
-----------------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |28     |
|Nextdoor, Airbnb, Quora |No    |11     |
|Windows Task Scheduler  |Yes   |17     |
|fcron                   |No    |10 (11)|
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

Built-in, customizable automatic termination support can be set up in xcron and Windows Task Scheduler per job and a point is therefore awarded to each.

Q31 - Can terminate a job after a set amount of output?
-------------------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |29     |
|Nextdoor, Airbnb, Quora |No    |11     |
|Windows Task Scheduler  |No    |17     |
|fcron                   |No    |10 (11)|
|Vixie/POSIX cron        |No    |6 (9)  |
|Koenig/POSIX at (atd)   |No    |7      |

When a script suddenly starts churning out massive amounts of unexpected output, xcron jobs can be configured to terminate a process long before it would fill up all available disk storage.  xcron gets a point.

Q32 - System resource usage checked before starting a job?
----------------------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |No*   |29     |
|Nextdoor, Airbnb, Quora |No    |11     |
|Windows Task Scheduler  |Yes   |18     |
|fcron                   |Yes   |11 (12)|
|Vixie/POSIX cron        |No    |6 (10) |
|Koenig/POSIX at (atd)   |Yes   |8      |

Options exist in Windows Task Scheduler, fcron, and Koenig/POSIX atd to check current CPU, RAM, and/or disk I/O usage before starting a job and each get a point.

xcron does not implement direct support at this time for global system resource checking but has hints of such support.

xcron does globally limit its maximum running process queue size to aid in controlling resource consumption but that is not direct support and therefore no point is given.

Q33 - Supports notifying via email?
-----------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |30     |
|Nextdoor, Airbnb, Quora |Yes   |12     |
|Windows Task Scheduler  |No*   |18     |
|fcron                   |Yes   |12 (13)|
|Vixie/POSIX cron        |Yes   |7 (11) |
|Koenig/POSIX at (atd)   |Yes   |9      |

Every cron system appears to have the ability to send email notifications whenever a job runs.  Whether or not that is actually useful is up for debate.

However, Windows Task Scheduler direct email notification support is marked as deprecated and may be removed in a future version of Windows and thus does not get a point.

Q34 - Supports notifying via other popular mediums?
---------------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |31     |
|Nextdoor, Airbnb, Quora |No?   |12     |
|Windows Task Scheduler  |No    |18     |
|fcron                   |No    |12 (13)|
|Vixie/POSIX cron        |No    |7 (11) |
|Koenig/POSIX at (atd)   |No    |9      |

xcron supports sending notifications to Slack and Discord channels and hence it gets a point.

The Nextdoor/Airbnb/Quora distributed systems could potentially also already send notifications to Discord, Slack, and similar chat systems but information about such support was lacking.

Q35 - Easily add new notification types?
----------------------------------------

Type:  Attribute

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |32     |
|Nextdoor, Airbnb, Quora |Yes?  |13     |
|Windows Task Scheduler  |No    |18     |
|fcron                   |No    |12 (13)|
|Vixie/POSIX cron        |No    |7 (11) |
|Koenig/POSIX at (atd)   |No    |9      |

xcron supports easily adding new notification types and gets another point.

The distributed systems are written in scripting languages which should lend themselves to adding new notification types fairly easily.  So they get a point here as well.

Q36 - Easily verify that job notifications are working?
-------------------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |33     |
|Nextdoor, Airbnb, Quora |No    |13     |
|Windows Task Scheduler  |No    |18     |
|fcron                   |No    |12 (13)|
|Vixie/POSIX cron        |No    |7 (11) |
|Koenig/POSIX at (atd)   |No    |9      |

xcron allows for sending test notifications to configured notification targets for each job.  xcron gets a point.

Q37 - Has notification flood limit support?
-------------------------------------------

Type:  Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |34     |
|Nextdoor, Airbnb, Quora |No    |13     |
|Windows Task Scheduler  |No    |18     |
|fcron                   |No    |12 (13)|
|Vixie/POSIX cron        |No    |7 (11) |
|Koenig/POSIX at (atd)   |No    |9      |

Only notifying when a state change happens and limiting sequential error messages avoids sending a constant flood of notifications, clogging up inboxes, and building up a general numbness to respond to repeated error conditions.

xcron only sends notifications when job state changes between success and failure as well as offering limits on the number of sequential errors that are sent.  Therefore, xcron gets a point.

Q38 - Core product easily extended?
-----------------------------------

Type:  Attribute

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |35     |
|Nextdoor, Airbnb, Quora |Yes   |14     |
|Windows Task Scheduler  |No    |18     |
|fcron                   |No    |12 (13)|
|Vixie/POSIX cron        |No    |7 (11) |
|Koenig/POSIX at (atd)   |No    |9      |

xcron has a custom plugin and notification system built-in making it easy to extend the product without modifying the core product.

xcron can be extended to do things such as remote monitoring, distributed schedule execution, etc.

The Nextdoor/Airbnb/Quora distributed cron systems are written in scripting languages which lend themselves to extending those products relatively easily.

A point is awarded to each as a result.

Q39 - Easily view upcoming job start times/queued jobs/running jobs/software internals?
---------------------------------------------------------------------------------------

Type:  Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |36     |
|Nextdoor, Airbnb, Quora |Yes   |15     |
|Windows Task Scheduler  |Yes   |19     |
|fcron                   |Yes   |13 (14)|
|Vixie/POSIX cron        |No    |7 (12) |
|Koenig/POSIX at (atd)   |Yes   |10     |

Everyone except Vixie/POSIX cron gets a point.

xcron provides both extensive details about its software internals as well as currently running jobs, queued jobs, and upcoming job start times.

The distributed cron systems do not show details about the software's internals and generally do not have job queues.  However, their included GUI/web browser interfaces make it visually straightforward to view currently running and upcoming job start times.

schtasks.exe on Windows can show currently running jobs and upcoming job start times but not details about the software's internals.  Windows Task Scheduler does not appear to have job queues.

fcron can show currently running jobs, queued jobs, and upcoming job start times via fcrondyn but not details about the software's internals.

Koenig/POSIX atd can do the same via the atq command.

Q40 - Gathers and shows stats per job/schedule?
-----------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |37     |
|Nextdoor, Airbnb, Quora |No    |15     |
|Windows Task Scheduler  |No    |19     |
|fcron                   |No    |13 (14)|
|Vixie/POSIX cron        |No    |7 (12) |
|Koenig/POSIX at (atd)   |No    |10     |

xcron returns very detailed per-job/schedule stats such as run count, alerts, terminations, runtime, longest runtime, etc. broken down by total, since last boot, last day, and today.  xcron gets a point.

Unfortunately, none of the other products have built-in stats tracking capabilities, so they don't get any points.

Q41 - Supports gathering and showing custom stats per job/schedule?
-------------------------------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |38     |
|Nextdoor, Airbnb, Quora |No    |15     |
|Windows Task Scheduler  |No    |19     |
|fcron                   |No    |13 (14)|
|Vixie/POSIX cron        |No    |7 (12) |
|Koenig/POSIX at (atd)   |No    |10     |

Jobs that run via xcron can return a serialized JSON object as the last line of non-empty output.

That JSON may contain custom stats such as CPU, RAM, disk I/O usage, number of rows processed, or whatever else is desired to be monitored over time for the job.  xcron gets another point.

Q42 - Supports temporarily suspending a job for a set amount of time?
---------------------------------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |39     |
|Nextdoor, Airbnb, Quora |No    |15     |
|Windows Task Scheduler  |No    |19     |
|fcron                   |No    |13 (14)|
|Vixie/POSIX cron        |No    |7 (12) |
|Koenig/POSIX at (atd)   |No    |10     |

This is not disabling/enabling or removing/adding a job but rather temporarily suspending a job schedule until a set time in the future.

xcron can temporarily push back a specific job schedule for a set amount of time thereby allowing a failing job script to be more easily debugged without interference from the job scheduler.  xcron gets a point.

Q43 - Supports triggered jobs?
------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |40     |
|Nextdoor, Airbnb, Quora |Yes   |16     |
|Windows Task Scheduler  |Yes   |20     |
|fcron                   |Yes   |14 (15)|
|Vixie/POSIX cron        |No    |7 (13) |
|Koenig/POSIX at (atd)   |Yes   |11     |

xcron, Nextdoor/Airbnb/Quora, Windows Task Scheduler, fcron, and Koenig/POSIX atd all have a mechanism to manually trigger a job to run as soon as possible.  Each one gets a point.

Q44 - Triggered jobs can use password protection for alternate user access?
---------------------------------------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |41     |
|Nextdoor, Airbnb, Quora |No    |16     |
|Windows Task Scheduler  |No    |20     |
|fcron                   |No    |14 (15)|
|Vixie/POSIX cron        |No    |7 (13) |
|Koenig/POSIX at (atd)   |No    |11     |

Each job in xcron may have its own separate optional password that is required to trigger that specific job as that user.

None of the other products have this feature so just xcron gets a point.

Q45 - Can pass custom data to triggered jobs?
---------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |42     |
|Nextdoor, Airbnb, Quora |No    |16     |
|Windows Task Scheduler  |No    |20     |
|fcron                   |No    |14 (16)|
|Vixie/POSIX cron        |No    |7 (14) |
|Koenig/POSIX at (atd)   |Yes   |12     |

Triggered jobs in xcron can be sent up to 16KB of a serialized JSON object that the script can access via the XCRON_DATA environment variable that is passed in from xcron.

Koenig/POSIX atd jobs are essentially a series of commands executed in a shell, which means custom data can be piped into executed commands.

xcron and atd each get a point.

Q46 - Can control triggered queue size per job/schedule?
--------------------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |43     |
|Nextdoor, Airbnb, Quora |No    |16     |
|Windows Task Scheduler  |No    |20     |
|fcron                   |No    |14 (16)|
|Vixie/POSIX cron        |No    |7 (14) |
|Koenig/POSIX at (atd)   |No    |12     |

The maximum triggered queue size can be set per job schedule in xcron.  xcron racks up another point.

Q47 - Emits a usable API?
-------------------------

Type:  Attribute/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |44     |
|Nextdoor, Airbnb, Quora |Yes   |17     |
|Windows Task Scheduler  |Yes   |21     |
|fcron                   |Yes   |15 (17)|
|Vixie/POSIX cron        |No    |7 (14) |
|Koenig/POSIX at (atd)   |No?   |12     |

xcron has a TCP/IP API, Nextdoor/Airbnb/Quora have REST APIs, Windows Task Scheduler has a COM API, and fcron has a Unix socket-based API.

There are pros and cons to each approach, but each one gets a point.

Q48 - Supports dynamic scheduling?
----------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |45     |
|Nextdoor, Airbnb, Quora |No    |17     |
|Windows Task Scheduler  |No    |21     |
|fcron                   |No    |15 (18)|
|Vixie/POSIX cron        |No    |7 (15) |
|Koenig/POSIX at (atd)   |Yes   |13     |

Hundreds of millions of servers have at least one cron job that runs very frequently:

```
* * * * * /path/to/my_script
*/2 * * * * /path/to/my_script
*/5 * * * * /path/to/my_script
*/10 * * * * /path/to/my_script
*/15 * * * * /path/to/my_script
*/20 * * * * /path/to/my_script
*/30 * * * * /path/to/my_script
```

Such schedules tend to accomplish no real work 99.99% of the time.
A script starts, ultimately determines that there is nothing to do, and shuts down.
The process repeats every minute or every few minutes.

```
* * * * *
60 * 24 * 365 = 525,600 script runs per year
```

A fairly common job schedule is one that run every minute, which results in approximately 525,600 program runs per year.

```
* * * * *
60 * 24 * 365 = 525,600 script runs per year * 250 million servers = 210 trillion script runs/year globally
```

The result is approximately 210 trillion program runs per year globally per job.  At scale, this type of job is a completely inefficient and wasteful use of Earth's resources.

Dynamic scheduling, on the other hand, is where the script schedules itself to only run when there is actual work to do.

xcron and Koenig/POSIX atd have dynamic scheduling as a core feature and each get a point.

Q49 - Supports live monitoring of per-process stdout/stderr?
------------------------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |46     |
|Nextdoor, Airbnb, Quora |No    |17     |
|Windows Task Scheduler  |Yes   |22     |
|fcron                   |No    |15 (19)|
|Vixie/POSIX cron        |No    |7 (16) |
|Koenig/POSIX at (atd)   |Yes   |14     |

xcron supports this via its API and xcrontab.

Windows Task Scheduler somewhat supports this when running console processes on the desktop.

Koenig/POSIX atd somewhat supports this via the temporary files that are placed in the `/var/spool/cron/atspool` directory.

All three get a point.

Q50 - Supports live monitoring of internal software state changes?
------------------------------------------------------------------

Type:  Feature/Attribute

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |47     |
|Nextdoor, Airbnb, Quora |No    |17     |
|Windows Task Scheduler  |No    |22     |
|fcron                   |No    |15 (19)|
|Vixie/POSIX cron        |No    |7 (16) |
|Koenig/POSIX at (atd)   |No    |14     |

xcron supports this via its API and xcrontab.  xcron gets a point.

Q51 - Supports retrieving last job run output?
----------------------------------------------

Type:  Feature

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |48     |
|Nextdoor, Airbnb, Quora |Yes?  |18     |
|Windows Task Scheduler  |No    |22     |
|fcron                   |No    |15 (19)|
|Vixie/POSIX cron        |No    |7 (16) |
|Koenig/POSIX at (atd)   |No    |14     |

xcron supports this via its API and xcrontab.  Nextdoor/Airbnb/Quora and other distributed systems possibly have this feature.  Both get a point.

Q52 - Supports retrieving last failed job run output?
-----------------------------------------------------

Type:  Feature/Behavior

|Product                 |Answer|Tally  |
|------------------------|------|-------|
|xcron                   |Yes   |49     |
|Nextdoor, Airbnb, Quora |No    |18     |
|Windows Task Scheduler  |No    |22     |
|fcron                   |No    |15 (19)|
|Vixie/POSIX cron        |No    |7 (16) |
|Koenig/POSIX at (atd)   |No    |14     |

xcron copies the output to a separate file on disk when an error state occurs for a job and makes it available via the xcron API and xcrontab.

Useful for when a job fails but the next job run succeeds and would have overwritten the previous run's error output.  xcron gets yet another point.

Final Scores/Grades
-------------------

Okay, the final scores are in!  There were 52 questions total.  Using a standard grading scale, here are the final scores, calculated percentages, and final letter grades:

|Product                 |Scores |Percent      |Grade|
|------------------------|-------|-------------|-----|
|xcron                   |49     |94.2%        |A    |
|Nextdoor, Airbnb, Quora |18     |34.6%        |F    |
|Windows Task Scheduler  |22     |42.3%        |F    |
|fcron                   |15 (19)|28.8% (36.5%)|F    |
|systemd timers          |16 (18)|30.8% (34.6%)|F    |
|Vixie/POSIX cron        |7 (16) |13.5% (30.8%)|F    |
|Koenig/POSIX at (atd)   |14     |26.9%        |F    |

xcron is 94.2% JSFABS-compliant and receives an A.

Even considering the combined score with Koenig/POSIX atd (i.e. the numbers in parenthesis), the rest of the cron/Task Scheduling solutions each receive an F.
