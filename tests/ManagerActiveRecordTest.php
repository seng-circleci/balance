<?php

namespace yii2tech\tests\unit\balance;

use Yii;
use yii2tech\balance\ManagerActiveRecord;
use yii2tech\tests\unit\balance\data\BalanceAccount;
use yii2tech\tests\unit\balance\data\BalanceTransaction;

/**
 * @group db
 */
class ManagerActiveRecordTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->setupTestDbData();
    }

    /**
     * Setup tables for test ActiveRecord
     */
    protected function setupTestDbData()
    {
        $db = Yii::$app->getDb();

        // Structure :

        $table = 'BalanceAccount';
        $columns = [
            'id' => 'pk',
            'userId' => 'integer',
            'balance' => 'integer DEFAULT 0',
        ];
        $db->createCommand()->createTable($table, $columns)->execute();

        $table = 'BalanceTransaction';
        $columns = [
            'id' => 'pk',
            'date' => 'integer',
            'accountId' => 'integer',
            'amount' => 'integer',
            'data' => 'text',
        ];
        $db->createCommand()->createTable($table, $columns)->execute();
    }

    /**
     * @return array last saved transaction data.
     */
    protected function getLastTransaction()
    {
        return BalanceTransaction::find()
            ->orderBy(['id' => SORT_DESC])
            ->limit(1)
            ->asArray(true)
            ->one();
    }

    /**
     * @return ManagerActiveRecord test manager instance.
     */
    protected function createManager()
    {
        $manager = new ManagerActiveRecord();
        $manager->accountClass = BalanceAccount::className();
        $manager->transactionClass = BalanceTransaction::className();
        return $manager;
    }

    // Tests :

    public function testIncrease()
    {
        $manager = $this->createManager();

        $manager->increase(1, 50);
        $transaction = $this->getLastTransaction();
        $this->assertEquals(50, $transaction['amount']);

        $manager->increase(1, 50, ['extra' => 'custom']);
        $transaction = $this->getLastTransaction();
        $this->assertContains('custom', $transaction['data']);
    }

    /**
     * @depends testIncrease
     */
    public function testAutoCreateAccount()
    {
        $manager = $this->createManager();

        $manager->autoCreateAccount = true;
        $manager->increase(['userId' => 5], 10);
        $accounts = BalanceAccount::find()->all();
        $this->assertCount(1, $accounts);
        $this->assertEquals(5, $accounts[0]['userId']);

        $manager->autoCreateAccount = false;
        $this->setExpectedException('yii\base\InvalidParamException');
        $manager->increase(['userId' => 10], 10);
    }

    /**
     * @depends testAutoCreateAccount
     */
    public function testIncreaseAccountBalance()
    {
        $manager = $this->createManager();
        $manager->autoCreateAccount = true;
        $manager->accountBalanceAttribute = 'balance';

        $amount = 50;
        $manager->increase(['userId' => 1], $amount);
        $account = BalanceAccount::find()->andWhere(['userId' => 1])->one();

        $this->assertEquals($amount, $account['balance']);
    }

    /**
     * @depends testIncrease
     */
    public function testRevert()
    {
        $manager = $this->createManager();

        $accountId = 1;
        $amount = 10;
        $transactionId = $manager->increase($accountId, $amount);
        $manager->revert($transactionId);

        $transaction = $this->getLastTransaction();
        $this->assertEquals($accountId, $transaction['accountId']);
        $this->assertEquals(-$amount, $transaction['amount']);
    }

    /**
     * @depends testIncrease
     */
    public function testCalculateBalance()
    {
        $manager = $this->createManager();

        $manager->increase(1, 50);
        $manager->increase(2, 50);
        $manager->decrease(1, 25);

        $this->assertEquals(25, $manager->calculateBalance(1));
    }
}