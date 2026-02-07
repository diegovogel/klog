// When an audio player starts playing, reset all other audio players.
document.addEventListener('play', function (e) {
    const players = document.querySelectorAll('audio')
    players.forEach(player => {
        if (player !== e.target) {
            player.pause()
            player.currentTime = 0
        }
    })
}, true)
