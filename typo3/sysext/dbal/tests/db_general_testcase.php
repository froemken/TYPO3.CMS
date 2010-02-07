<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Xavier Perseguers <typo3@perseguers.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


require_once('BaseTestCase.php');

/**
 * Testcase for class ux_t3lib_db.
 * 
 * $Id$
 *
 * @author Xavier Perseguers <typo3@perseguers.ch>
 *
 * @package TYPO3
 * @subpackage dbal
 */
class db_general_testcase extends BaseTestCase {

	/**
	 * @var t3lib_db
	 */
	protected $db;

	/**
	 * @var array
	 */
	protected $loadedExtensions;

	/** 
	 * @var array
	 */
	protected $temporaryFiles;

	/**
	 * Prepares the environment before running a test.
	 */
	public function setUp() {
			// Backup list of loaded extensions
		$this->loadedExtensions = $GLOBALS['TYPO3_LOADED_EXT'];
			// Backup database connection
		$this->db = $GLOBALS['TYPO3_DB'];
		$this->temporaryFiles = array();

		$className =  self::buildAccessibleProxy('ux_t3lib_db');
		$GLOBALS['TYPO3_DB'] = new $className;
		$GLOBALS['TYPO3_DB']->lastHandlerKey = '_DEFAULT';
	}

	/**
	 * Cleans up the environment after running a test.
	 */
	public function tearDown() {
			// Clear DBAL-generated cache files
		$GLOBALS['TYPO3_DB']->clearCachedFieldInfo();
			// Delete temporary files
		foreach ($this->temporaryFiles as $filename) unlink($filename);
			// Restore DB connection
		$GLOBALS['TYPO3_DB'] = $this->db;
			// Restore list of loaded extensions
		$GLOBALS['TYPO3_LOADED_EXT'] = $this->loadedExtensions;
	}

	/**
	 * Cleans a SQL query.
	 *  
	 * @param mixed $sql
	 * @return mixed (string or array)
	 */
	private function cleanSql($sql) {
		if (!is_string($sql)) {
			return $sql;
		}

		$sql = str_replace("\n", ' ', $sql);
		$sql = preg_replace('/\s+/', ' ', $sql);
		return trim($sql);
	}

	/**
	 * Creates a fake extension with a given table definition.
	 * 
	 * @param string $tableDefinition SQL script to create the extension's tables
	 * @return void
	 */
	protected function createFakeExtension($tableDefinition) {
			// Prepare a fake extension configuration
		$ext_tables = t3lib_div::tempnam('ext_tables');
		t3lib_div::writeFile($ext_tables, $tableDefinition);
		$this->temporaryFiles[] = $ext_tables;

		$GLOBALS['TYPO3_LOADED_EXT']['test_dbal'] = array(
			'ext_tables.sql' => $ext_tables
		);

			// Append our test table to the list of existing tables
		$GLOBALS['TYPO3_DB']->clearCachedFieldInfo();
		$GLOBALS['TYPO3_DB']->_call('initInternalVariables');
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12515
	 */
	public function concatCanBeParsedAfterLikeOperator() {
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'*',
			'sys_refindex, tx_dam_file_tracking',
			'sys_refindex.tablename = \'tx_dam_file_tracking\''
			. ' AND sys_refindex.ref_string LIKE CONCAT(tx_dam_file_tracking.file_path, tx_dam_file_tracking.file_name)'
		));
		$expected = 'SELECT * FROM sys_refindex, tx_dam_file_tracking WHERE sys_refindex.tablename = \'tx_dam_file_tracking\'';
		$expected .= ' AND sys_refindex.ref_string LIKE CONCAT(tx_dam_file_tracking.file_path, tx_dam_file_tracking.file_name)';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=10965
	 */
	public function floatNumberCanBeStoredInDatabase() {
		$this->createFakeExtension('
			CREATE TABLE tx_test_dbal (
				foo double default \'0\',
				foobar integer default \'0\'
			);
		');
		$data = array(
			'foo'    => 99.12,
			'foobar' => -120,
		);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->INSERTquery('tx_test_dbal', $data));
		$expected = 'INSERT INTO tx_test_dbal ( foo, foobar ) VALUES ( \'99.12\', \'-120\' )';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=11093
	 */
	public function positive64BitIntegerIsSupported() {
		$this->createFakeExtension('
			CREATE TABLE tx_test_dbal (
				foo int default \'0\',
				foobar bigint default \'0\'
			);
		');
		$data = array(
			'foo'    => 9223372036854775807,
			'foobar' => 9223372036854775807,
		);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->INSERTquery('tx_test_dbal', $data));
		$expected = 'INSERT INTO tx_test_dbal ( foo, foobar ) VALUES ( \'9223372036854775807\', \'9223372036854775807\' )';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=11093
	 */
	public function negative64BitIntegerIsSupported() {
		$this->createFakeExtension('
			CREATE TABLE tx_test_dbal (
				foo int default \'0\',
				foobar bigint default \'0\'
			);
		');
		$data = array(
			'foo'    => -9223372036854775808,
			'foobar' => -9223372036854775808,
		);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->INSERTquery('tx_test_dbal', $data));
		$expected = 'INSERT INTO tx_test_dbal ( foo, foobar ) VALUES ( \'-9223372036854775808\', \'-9223372036854775808\' )';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * http://bugs.typo3.org/view.php?id=12858
	 */
	public function sqlForInsertWithMultipleRowsIsValid() {
		$fields = array('uid', 'pid', 'title', 'body');
		$rows = array(
			array('1', '2', 'Title #1', 'Content #1'),
			array('3', '4', 'Title #2', 'Content #2'),
			array('5', '6', 'Title #3', 'Content #3'),
		);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->INSERTmultipleRows('tt_content', $fields, $rows));

		$expected = 'INSERT INTO tt_content (uid, pid, title, body) VALUES ';
		$expected .= "('1', '2', 'Title #1', 'Content #1'), ";
		$expected .= "('3', '4', 'Title #2', 'Content #2'), ";
		$expected .= "('5', '6', 'Title #3', 'Content #3')";

		$this->assertEquals($expected, $query);
	}
}
?>