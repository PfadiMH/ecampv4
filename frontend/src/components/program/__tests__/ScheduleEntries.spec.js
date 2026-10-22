import { describe, expect, it, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import ScheduleEntries from '@/components/program/ScheduleEntries.vue'

const collection = (loading) => ({ _meta: { loading }, items: [] })

describe('ScheduleEntries', () => {
  it('requests activities and categories while the schedule entries are still loading', () => {
    const activities = vi.fn(() => collection(true))
    const categories = vi.fn(() => collection(true))
    const period = {
      scheduleEntries: () => collection(true),
      camp: () => ({ activities, categories }),
    }

    const wrapper = shallowMount(ScheduleEntries, {
      props: { period, showButton: false },
      global: { stubs: { DialogActivityCreate: true } },
    })

    expect(wrapper.vm.loading).toBe(true)
    expect(activities).toHaveBeenCalled()
    expect(categories).toHaveBeenCalled()
  })
})
