@props(['id'])

<dialog id="{{ $id }}" class="modal candidate-vision-dialog" x-ref="visionDialog"
  aria-labelledby="{{ $id }}-title" x-on:close="visionCandidate = null">
  <div class="modal-header">
    <h2 id="{{ $id }}-title">Visi dan Misi Pasangan Calon</h2>
    <button type="button" class="icon-btn" aria-label="Tutup dialog" x-on:click="$refs.visionDialog.close()">
      <svg class="icon" aria-hidden="true"><use href="#i-x"></use></svg>
    </button>
  </div>
  <div class="modal-body">
    <div class="candidate-vision-heading">
      <template x-if="visionCandidate?.photo">
        <img :src="visionCandidate.photo" alt="" x-on:error="$el.style.display = 'none'" />
      </template>
      <div>
        <span class="candidate-kicker" x-text="'Pasangan Calon ' + (visionCandidate?.number ?? '')"></span>
        <h3 class="candidate-names" x-text="visionCandidate?.name"></h3>
      </div>
    </div>
    <div class="candidate-vision">
      <span>Visi</span>
      <p x-text="visionCandidate?.vision || 'Belum ada visi yang dicantumkan.'"></p>
    </div>
    <div class="candidate-vision">
      <span>Daftar Misi</span>
      <ul class="mission-list" x-show="visionCandidate?.missions.length">
        <template x-for="(mission, index) in visionCandidate?.missions ?? []" :key="index">
          <li x-text="mission"></li>
        </template>
      </ul>
      <p x-show="!visionCandidate?.missions.length">Belum ada misi yang dicantumkan.</p>
    </div>
  </div>
</dialog>
