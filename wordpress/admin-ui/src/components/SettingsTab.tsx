import type { Settings, SettingsChangeHandler } from '../types'
import { SetupWizard } from './SetupWizard'
import { StatusCard } from './StatusCard'
import { GatingToggle } from './GatingToggle'
import { PricingSection } from './PricingSection'
import { WalletSection } from './WalletSection'

interface Props {
  settings: Settings
  onSettingsChange: SettingsChangeHandler
}

export function SettingsTab({ settings, onSettingsChange }: Props) {
  if (!settings.is_registered || !settings.has_site) {
    return <SetupWizard settings={settings} onSettingsChange={onSettingsChange} />
  }

  return (
    <>
      <StatusCard settings={settings} />
      <GatingToggle settings={settings} onSettingsChange={onSettingsChange} />
      <PricingSection settings={settings} onSettingsChange={onSettingsChange} />
      <WalletSection settings={settings} onSettingsChange={onSettingsChange} />
    </>
  )
}
