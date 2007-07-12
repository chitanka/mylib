<?php
class EditLiterNewsPage extends LiternewsPage {

	public function __construct() {
		parent::__construct();
		$this->action = 'editLiternews';
		$this->title = 'Редактиране на литературни новини';
		$this->shownews = $this->request->checkbox('show', array($this->objId));
		$this->delnews = $this->request->checkbox('del', array($this->objId));
	}


	protected function processSubmission() {
		if (empty($this->objId)) return '';
		if ( empty($this->newsuser) || empty($this->newstext) ) {
			$this->addMessage('Попълнете всички полета!');
			return $this->buildContent();
		}
		require_once 'include/replace.php';
		$this->newstitle = my_replace($this->newstitle);
		$this->newstext = my_replace($this->newstext);
		if ( $this->request->value('preview') != NULL ) {
			$this->addMessage('Това е само предварителен преглед. Новината все още не е съхранена.');
			return $this->makeNewsEntry() . $this->makeEditForm(true);
		}
		$key = array('id' => $this->objId);
		if ($this->delnews) {
			$this->db->delete($this->mainDbTable, $key, 1);
			$this->addMessage('Новината беше изтрита.');
			return parent::buildContent();
		}
		$set = array('username' => $this->newsuser,
			'title' => $this->newstitle, 'text' => $this->newstext,
			'texthash' => md5($this->newstext), 'src' => $this->newssrc,
			'show' => $this->shownews);
		$this->db->update($this->mainDbTable, $set, $key);
		$this->addMessage('Новината беше съхранена.');
		$this->objId = 0;
		return $this->buildContent();
	}


	protected function buildContent() {
		if (empty($this->objId)) return parent::buildContent();
		$this->initData();
		return $this->makeEditForm(true);
	}


	protected function initData() {
		$sel = array('username newsuser', 'title newstitle', 'text newstext',
			'time newstime', '`show` shownews', 'src newssrc');
		$res = $this->db->select($this->mainDbTable, array('id'=>$this->objId), $sel);
		$data = $this->db->fetchAssoc($res);
		extract2object($data, $this);
	}

}
