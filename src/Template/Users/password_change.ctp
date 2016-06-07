<div class="users form">
<?= $this->Form->create('Users' , ['type' => 'put']) ?>
    <fieldset>
        <legend>Bitte geben Sie Ihren altes Password und Ihr neues Passwort ein.</legend>
        <?= $this->Form->input('password_old', ['label' => 'Altes Passwort']) ?>
        <?= $this->Form->input('password', ['label' => 'Neues Password']) ?>
        <?= $this->Form->input('password_confirm', ['label' => 'Neues Password wiederholen']) ?>
    </fieldset>
<?= $this->Form->button(__('Ändern')); ?>
<span class="secondary-button" style=""><?= $this->Html->link("Abbrechen", ['action' => 'login']) ?></span>
<?= $this->Form->end() ?>
</div>
