{extends "layout.tpl"}

{block "title"}Preferințe{/block}

{block "content"}
  {if User::getActive()}
    <div class="panel panel-default">
      <div class="panel-heading">Imagine</div>
      <div class="panel-body">
        <form action="editare-avatar" method="post" enctype="multipart/form-data">
          {include "bits/avatar.tpl" user=User::getActive()}
          <br>
          <br>
          <div class="form-group">
            <label for="avatarFileName">Fișier:</label>
            <input id="avatarFileName" type="file" name="avatarFileName">
          </div>
          <button id="avatarSubmit" class="btn btn-default" type="submit" name="submit" disabled>
            <i class="glyphicon glyphicon-pencil"></i>
            editează
          </button>
          {if User::getActive()->hasAvatar}
            <a href="salvare-avatar?delete=1"
               class="btn btn-danger"
               onclick="return confirm('Confirmați ștergerea imaginii?');">
              <i class="glyphicon glyphicon-trash"></i>
              șterge imaginea
            </a>
          {/if}

          <p class="help-block">
            Imaginea profilului dumneavoastră are rezoluția de 48x48 pixeli.
            Pe ecranul următor puteți edita poza încărcată.
          </p>
        </form>
      </div>
    </div>
  {/if}


  <form method="post" action="preferinte" name="accountForm">
    {if User::getActive()}
      <div class="panel panel-default">
        <div class="panel-heading">Date personale</div>
        <div class="panel-body">
          <div class="checkbox">
            <label>
              <input type="checkbox" name="detailsVisible" value="1" {if $detailsVisible}checked{/if}>
              Datele mele sunt vizibile public
              <span class="help-block">
                Identitatea OpenID, numele și adresa de email furnizate de OpenID vor apărea în
                <a href="{$wwwRoot}utilizator/{User::getActive()}">profilul dumneavoastră</a>.
                <em>dexonline</em> nu permite editarea directă a acestor date.<br>
                Ele sunt preluate din identitatea OpenID.
              </span>
            </label>
          </div>
        </div>
      </div>
    {/if}

    <div class="panel panel-default">
      <div class="panel-heading">Preferințe</div>
      <div class="panel-body">
        {foreach $userPrefs as $value => $i}
          <div class="checkbox {if !$i.enabled}disabled{/if}">
            <label>
              <input type="checkbox"
                     name="userPrefs[]"
                     value="{$value}"
                     {if !$i.enabled}disabled{/if}
                     {if $i.checked}checked{/if}>
              {$i.label}
              <span class="help-block">{$i.comment}</span>
            </label>
          </div>
        {/foreach}
      </div>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading">Elemente în pagina principală</div>
      <div class="panel-body">

        {foreach $widgets as $value => $w}
          <div class="checkbox">
            <label>
              <input type="checkbox" name="widgets[]" value="{$value}" {if $w.enabled}checked{/if}>
              {$w.name}
            </label>
          </div>
        {/foreach}
      </div>
    </div>

    {if User::can(User::PRIV_ANY)}
      <div class="panel panel-default">
        <div class="panel-heading">Privilegii</div>
        <div class="panel-body">
          <ul>
            {foreach User::$PRIV_NAMES as $mask => $privName}
              {if User::can($mask)}
                <li>{$privName}</li>
              {/if}
            {/foreach}
          </ul>
        </div>
      </div>
    {/if}

    <button class="btn btn-success" type="submit" name="saveButton">
      <i class="glyphicon glyphicon-floppy-disk"></i>
      salvează
    </button>
    {if User::getActive()}
      <a class="btn btn-link" href="{$wwwRoot}utilizator/{User::getActive()|escape}">renunță</a>
    {/if}

  </form>

  <script>
   $('#avatarFileName').change(function() {
     var error = '';
     var allowedTypes = ['image/gif', 'image/jpeg', 'image/png'];
     if (this.files[0].size > (1 << 21)) {
       error = 'Dimensiunea maximă admisă este 2 MB.';
     } else if (allowedTypes.indexOf(this.files[0].type) == -1) {
       error = 'Sunt permise doar imagini jpeg, png sau gif.';
     }
     if (error) {
       $('#avatarFileName').val('');
       $('#avatarSubmit').attr('disabled', 'disabled');
       alert(error);
     } else {
       $('#avatarSubmit').removeAttr('disabled');
     }
     return false;
   });
  </script>
{/block}
