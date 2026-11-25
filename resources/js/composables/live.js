import { liveQuery } from 'dexie'
import { onUnmounted, ref } from 'vue'

export function useLiveQuery(querier) {
    const data = ref(null)
    const subscription = liveQuery(querier).subscribe({
        next(value) {
            data.value = value
        },
        error(error) {
            console.error(error)
        },
    })
    onUnmounted(() => subscription.unsubscribe())
    return data
}
