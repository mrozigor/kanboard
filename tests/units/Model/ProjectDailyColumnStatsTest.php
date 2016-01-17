<?php

require_once __DIR__.'/../Base.php';

use Kanboard\Model\Project;
use Kanboard\Model\ProjectDailyColumnStats;
use Kanboard\Model\Config;
use Kanboard\Model\Task;
use Kanboard\Model\TaskCreation;
use Kanboard\Model\TaskStatus;

class ProjectDailyColumnStatsTest extends Base
{
    public function testUpdateTotals()
    {
        $projectModel = new Project($this->container);
        $projectDailyColumnStats = new ProjectDailyColumnStats($this->container);
        $this->assertEquals(1, $projectModel->create(array('name' => 'UnitTest')));

        $this->createTasks(1, 2, 1);
        $this->createTasks(1, 3, 0);

        $this->createTasks(2, 5, 1);
        $this->createTasks(2, 8, 1);
        $this->createTasks(2, 0, 0);
        $this->createTasks(2, 0, 0);

        $projectDailyColumnStats->updateTotals(1, '2016-01-16');

        $this->createTasks(1, 9, 1);
        $this->createTasks(1, 7, 0);

        $projectDailyColumnStats->updateTotals(1, '2016-01-16');

        $this->createTasks(3, 0, 1);

        $projectDailyColumnStats->updateTotals(1, '2016-01-17');

        $stats = $this->container['db']->table(ProjectDailyColumnStats::TABLE)
            ->asc('day')
            ->asc('column_id')
            ->columns('day', 'project_id', 'column_id', 'total', 'score')
            ->findAll();

        $expected = array(
            array(
                'day' => '2016-01-16',
                'project_id' => 1,
                'column_id' => 1,
                'total' => 4,
                'score' => 11,
            ),
            array(
                'day' => '2016-01-16',
                'project_id' => 1,
                'column_id' => 2,
                'total' => 4,
                'score' => 13,
            ),
            array(
                'day' => '2016-01-17',
                'project_id' => 1,
                'column_id' => 1,
                'total' => 4,
                'score' => 11,
            ),
            array(
                'day' => '2016-01-17',
                'project_id' => 1,
                'column_id' => 2,
                'total' => 4,
                'score' => 13,
            ),
            array(
                'day' => '2016-01-17',
                'project_id' => 1,
                'column_id' => 3,
                'total' => 1,
                'score' => 0,
            ),
        );

        $this->assertEquals($expected, $stats);
    }

    public function testUpdateTotalsWithOnlyOpenTasks()
    {
        $configModel = new Config($this->container);
        $projectModel = new Project($this->container);
        $projectDailyColumnStats = new ProjectDailyColumnStats($this->container);

        $this->assertEquals(1, $projectModel->create(array('name' => 'UnitTest')));
        $this->assertTrue($configModel->save(array('cfd_include_closed_tasks' => 0)));
        $this->container['memoryCache']->flush();

        $this->createTasks(1, 2, 1);
        $this->createTasks(1, 3, 0);

        $this->createTasks(2, 5, 1);
        $this->createTasks(2, 8, 1);
        $this->createTasks(2, 0, 0);
        $this->createTasks(2, 0, 0);

        $projectDailyColumnStats->updateTotals(1, '2016-01-16');

        $this->createTasks(1, 9, 1);
        $this->createTasks(1, 7, 0);

        $projectDailyColumnStats->updateTotals(1, '2016-01-16');

        $this->createTasks(3, 0, 1);

        $projectDailyColumnStats->updateTotals(1, '2016-01-17');

        $stats = $this->container['db']->table(ProjectDailyColumnStats::TABLE)
            ->asc('day')
            ->asc('column_id')
            ->columns('day', 'project_id', 'column_id', 'total', 'score')
            ->findAll();

        $expected = array(
            array(
                'day' => '2016-01-16',
                'project_id' => 1,
                'column_id' => 1,
                'total' => 2,
                'score' => 11,
            ),
            array(
                'day' => '2016-01-16',
                'project_id' => 1,
                'column_id' => 2,
                'total' => 2,
                'score' => 13,
            ),
            array(
                'day' => '2016-01-17',
                'project_id' => 1,
                'column_id' => 1,
                'total' => 2,
                'score' => 11,
            ),
            array(
                'day' => '2016-01-17',
                'project_id' => 1,
                'column_id' => 2,
                'total' => 2,
                'score' => 13,
            ),
            array(
                'day' => '2016-01-17',
                'project_id' => 1,
                'column_id' => 3,
                'total' => 1,
                'score' => 0,
            ),
        );

        $this->assertEquals($expected, $stats);
    }

    public function testCountDays()
    {
        $projectModel = new Project($this->container);
        $projectDailyColumnStats = new ProjectDailyColumnStats($this->container);

        $this->assertEquals(1, $projectModel->create(array('name' => 'UnitTest')));

        $this->createTasks(1, 2, 1);
        $projectDailyColumnStats->updateTotals(1, '2016-01-16');
        $this->assertEquals(1, $projectDailyColumnStats->countDays(1, '2016-01-16', '2016-01-17'));

        $projectDailyColumnStats->updateTotals(1, '2016-01-17');
        $this->assertEquals(2, $projectDailyColumnStats->countDays(1, '2016-01-16', '2016-01-17'));
    }

    public function testGetAggregatedMetrics()
    {
        $projectModel = new Project($this->container);
        $projectDailyColumnStats = new ProjectDailyColumnStats($this->container);
        $this->assertEquals(1, $projectModel->create(array('name' => 'UnitTest')));

        $this->createTasks(1, 2, 1);
        $this->createTasks(1, 3, 0);

        $this->createTasks(2, 5, 1);
        $this->createTasks(2, 8, 1);
        $this->createTasks(2, 0, 0);
        $this->createTasks(2, 0, 0);

        $projectDailyColumnStats->updateTotals(1, '2016-01-16');

        $this->createTasks(1, 9, 1);
        $this->createTasks(1, 7, 0);

        $projectDailyColumnStats->updateTotals(1, '2016-01-16');

        $this->createTasks(3, 0, 1);

        $projectDailyColumnStats->updateTotals(1, '2016-01-17');

        $this->createTasks(2, 1, 1);
        $this->createTasks(3, 1, 1);
        $this->createTasks(3, 0, 1);

        $projectDailyColumnStats->updateTotals(1, '2016-01-18');

        $expected = array(
            array('Date', 'Backlog', 'Ready', 'Work in progress', 'Done'),
            array('2016-01-16', 4, 4, 0, 0),
            array('2016-01-17', 4, 4, 1, 0),
            array('2016-01-18', 4, 5, 3, 0),
        );

        $this->assertEquals($expected, $projectDailyColumnStats->getAggregatedMetrics(1, '2016-01-16', '2016-01-18'));

        $expected = array(
            array('Date', 'Backlog', 'Ready', 'Work in progress', 'Done'),
            array('2016-01-16', 11, 13, 0, 0),
            array('2016-01-17', 11, 13, 0, 0),
            array('2016-01-18', 11, 14, 1, 0),
        );

        $this->assertEquals($expected, $projectDailyColumnStats->getAggregatedMetrics(1, '2016-01-16', '2016-01-18', 'score'));
    }

    private function createTasks($column_id, $score, $is_active)
    {
        $taskCreationModel = new TaskCreation($this->container);
        $this->assertNotFalse($taskCreationModel->create(array('project_id' => 1, 'title' => 'test', 'column_id' => $column_id, 'score' => $score, 'is_active' => $is_active)));
    }
}
