import { defineStore } from 'pinia'

export const useUiStore = defineStore('ui', {
    state: () => ({
        notice: '',
        kind: 'ok',
        timer: null,
    }),
    actions: {
        flash(message, kind = 'ok') {
            this.notice = message
            this.kind = kind
            clearTimeout(this.timer)
            this.timer = setTimeout(() => {
                this.notice = ''
            }, 4500)
        },
    },
})
