<?php
namespace Tests\Feature;
use App\Models\{Role,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
class RoleDashboardTest extends TestCase { use RefreshDatabase;
 private function user(string $slug,array $permissions=[]):User{$role=Role::create(['name'=>$slug,'slug'=>$slug,'permissions'=>$permissions,'is_active'=>true]);$user=User::factory()->create(['password'=>Hash::make('test-password')]);$user->roles()->attach($role);return $user;}
 public function test_admin_login_redirects_to_admin_dashboard():void{$u=$this->user('super_admin',['*']);$this->post('/login',['email'=>$u->email,'password'=>'test-password'])->assertRedirect('/admin/dashboard');$this->actingAs($u)->get('/admin/dashboard')->assertOk()->assertSee('Administrator Command Center');}
 public function test_mao_login_redirects_to_mao_dashboard():void{$u=$this->user('municipal_agriculturist',['dashboard.view','modules.ffrms.*']);$this->post('/login',['email'=>$u->email,'password'=>'test-password'])->assertRedirect('/mao/dashboard');$this->actingAs($u)->get('/mao/dashboard')->assertOk()->assertSee('MAO Operations Dashboard');}
 public function test_mao_admin_login_redirects_to_mao_dashboard():void{$u=$this->user('mao_admin',['dashboard.view','settings.manage']);$this->post('/login',['email'=>$u->email,'password'=>'test-password'])->assertRedirect('/mao/dashboard');}
 public function test_authorized_viewer_login_redirects_to_read_only_dashboard():void{$u=$this->user('authorized_viewer',['dashboard.view']);$this->post('/login',['email'=>$u->email,'password'=>'test-password'])->assertRedirect('/dashboard');}
 public function test_farmer_cannot_open_staff_dashboards():void{$u=$this->user('farmer',['farmer.profile.manage']);$this->actingAs($u)->get('/admin/dashboard')->assertForbidden();$this->actingAs($u)->get('/mao/dashboard')->assertForbidden();}
 public function test_login_page_and_logout_work():void{$u=$this->user('super_admin',['*']);$this->get('/login')->assertOk()->assertSee('Show password');$this->actingAs($u)->post('/logout')->assertRedirect('/login');$this->assertGuest();}
}
